/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** URL da API Laravel (sem /api/v1). Ex.: http://localhost:8000 */
  readonly VITE_API_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
