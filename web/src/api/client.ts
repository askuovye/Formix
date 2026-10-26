import axios, { AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'

import { API_URL } from '@/config/env'

/** Erro da API num formato único, independente do Axios. */
export class ApiError extends Error {
  constructor(
    message: string,
    /** Código HTTP; 0 quando não houve resposta (rede, CORS, servidor fora). */
    readonly status: number,
    /** Erros de validação (422) por campo: `{ email: ['…'] }`. */
    readonly fieldErrors: Record<string, string[]> = {},
    /** Segundos até poder tentar de novo (429). */
    readonly retryAfter?: number,
  ) {
    super(message)
    this.name = 'ApiError'
  }

  /** Primeira mensagem de cada campo, no formato que os componentes de campo usam. */
  get firstFieldErrors(): Record<string, string> {
    return Object.fromEntries(
      Object.entries(this.fieldErrors).flatMap(([field, messages]) =>
        messages[0] ? [[field, messages[0]]] : [],
      ),
    )
  }
}

const SAFE_METHODS = new Set(['get', 'head', 'options'])

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

let csrfReady: Promise<void> | null = null
let onUnauthorized: (() => void) | null = null

/** Única instância do Axios do app (AGENTS.md §7). */
export const http: AxiosInstance = axios.create({
  baseURL: `${API_URL}/api/v1`,
  withCredentials: true,
  // A API está em outro subdomínio: sem isto o Axios não envia o X-XSRF-TOKEN.
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

/** Pede o cookie XSRF-TOKEN ao Sanctum (uma vez, ou de novo após um 419). */
export function ensureCsrfCookie(): Promise<void> {
  csrfReady ??= axios
    .get(`${API_URL}/sanctum/csrf-cookie`, { withCredentials: true })
    .then(() => undefined)
    .catch((error: unknown) => {
      csrfReady = null
      throw toApiError(error)
    })

  return csrfReady
}

/** A sessão registra aqui o que fazer quando a API responde 401. */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler
}

http.interceptors.request.use(async (config) => {
  if (!SAFE_METHODS.has((config.method ?? 'get').toLowerCase())) {
    await ensureCsrfCookie()
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  async (error: unknown) => {
    if (error instanceof AxiosError && error.response && error.config) {
      const config = error.config as RetriableConfig

      // 419: token CSRF ou sessão expirados. Renova o cookie e repete uma vez.
      if (error.response.status === 419 && !config._csrfRetried) {
        config._csrfRetried = true
        csrfReady = null
        await ensureCsrfCookie()

        return http.request(config)
      }

      if (error.response.status === 401) {
        onUnauthorized?.()
      }
    }

    throw toApiError(error)
  },
)

interface ErrorBody {
  message?: unknown
  errors?: unknown
}

export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) {
    return error
  }

  if (!(error instanceof AxiosError)) {
    return new ApiError('Erro inesperado.', 0)
  }

  const response = error.response

  if (!response) {
    return new ApiError('Não foi possível falar com o servidor. Verifique sua conexão.', 0)
  }

  const body = (response.data ?? {}) as ErrorBody
  const message = typeof body.message === 'string' ? body.message : 'Erro inesperado.'
  const retryAfterHeader = Number(response.headers['retry-after'])

  return new ApiError(
    message,
    response.status,
    isFieldErrors(body.errors) ? body.errors : {},
    Number.isFinite(retryAfterHeader) ? retryAfterHeader : undefined,
  )
}

function isFieldErrors(value: unknown): value is Record<string, string[]> {
  return (
    typeof value === 'object' &&
    value !== null &&
    Object.values(value).every(
      (messages) => Array.isArray(messages) && messages.every((item) => typeof item === 'string'),
    )
  )
}
