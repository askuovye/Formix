/** URL base da API Laravel (sem /api/v1). Padrão: a API local do compose. */
export const API_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000').replace(/\/+$/, '')
