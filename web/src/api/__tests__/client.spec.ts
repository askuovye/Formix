import axios, {
  AxiosError,
  AxiosHeaders,
  type AxiosAdapter,
  type InternalAxiosRequestConfig,
} from 'axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError, http, setUnauthorizedHandler, toApiError } from '../client'

interface FakeResponse {
  status: number
  data?: unknown
  headers?: Record<string, string>
}

/** Adapter falso: responde em sequência e registra as chamadas. */
function fakeAdapter(responses: FakeResponse[]): {
  adapter: AxiosAdapter
  calls: InternalAxiosRequestConfig[]
} {
  const calls: InternalAxiosRequestConfig[] = []

  const adapter: AxiosAdapter = async (config) => {
    calls.push(config)
    const next = responses.shift() ?? { status: 200 }
    const response = {
      data: next.data ?? {},
      status: next.status,
      statusText: '',
      headers: new AxiosHeaders(next.headers ?? {}),
      config,
    }

    if (next.status >= 400) {
      throw new AxiosError('erro', String(next.status), config, null, response)
    }

    return response
  }

  return { adapter, calls }
}

const originalHttpAdapter = http.defaults.adapter
const originalAxiosAdapter = axios.defaults.adapter

function install(responses: FakeResponse[]): InternalAxiosRequestConfig[] {
  const { adapter, calls } = fakeAdapter(responses)
  http.defaults.adapter = adapter
  axios.defaults.adapter = adapter
  return calls
}

describe('cliente HTTP', () => {
  beforeEach(() => {
    setUnauthorizedHandler(null)
  })

  afterEach(() => {
    http.defaults.adapter = originalHttpAdapter
    axios.defaults.adapter = originalAxiosAdapter
  })

  it('envia cookies e pede o cookie CSRF antes do primeiro POST', async () => {
    const calls = install([{ status: 204 }, { status: 200 }])

    await http.post('/auth/logout')

    expect(calls.map((call) => call.url)).toEqual([
      'http://localhost:8000/sanctum/csrf-cookie',
      '/auth/logout',
    ])
    expect(calls[1]?.withCredentials).toBe(true)
  })

  it('GET não pede cookie CSRF', async () => {
    const calls = install([{ status: 200 }])

    await http.get('/legal/terms')

    expect(calls.map((call) => call.url)).toEqual(['/legal/terms'])
  })

  it('419: renova o cookie CSRF e repete a requisição uma vez', async () => {
    // O cookie da sessão já foi pedido no teste anterior.
    const calls = install([{ status: 419 }, { status: 204 }, { status: 200 }])

    const response = await http.post('/auth/logout')

    expect(response.status).toBe(200)
    expect(calls.map((call) => call.url)).toEqual([
      '/auth/logout',
      'http://localhost:8000/sanctum/csrf-cookie',
      '/auth/logout',
    ])
  })

  it('419 de novo após repetir: desiste com ApiError', async () => {
    install([{ status: 419 }, { status: 204 }, { status: 419, data: { message: 'Expirou.' } }])

    await expect(http.post('/auth/logout')).rejects.toMatchObject({ status: 419 })
  })

  it('401 chama o handler de sessão encerrada', async () => {
    const handler = vi.fn()
    setUnauthorizedHandler(handler)
    install([{ status: 401, data: { message: 'Unauthenticated.' } }])

    await expect(http.get('/auth/me')).rejects.toBeInstanceOf(ApiError)
    expect(handler).toHaveBeenCalledOnce()
  })

  it('422: mensagem e erros por campo', async () => {
    install([
      {
        status: 422,
        data: { message: 'Dados inválidos.', errors: { email: ['Email inválido.', 'Outro.'] } },
      },
    ])

    const error = await http.get('/x').catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 422, message: 'Dados inválidos.' })
    expect((error as ApiError).firstFieldErrors).toEqual({ email: 'Email inválido.' })
  })

  it('429: lê o Retry-After', async () => {
    install([
      { status: 429, data: { message: 'Muitas tentativas.' }, headers: { 'retry-after': '42' } },
    ])

    await expect(http.get('/x')).rejects.toMatchObject({ status: 429, retryAfter: 42 })
  })

  it('sem resposta (rede): status 0 e mensagem amigável', () => {
    const error = toApiError(new AxiosError('Network Error', 'ERR_NETWORK'))

    expect(error.status).toBe(0)
    expect(error.message).toContain('conexão')
  })
})
