/** Para onde ir depois de entrar, quando nada for pedido. */
export const DEFAULT_AFTER_LOGIN = '/painel'

/**
 * Aceita só caminhos internos vindos de `?redirect=`: começa com uma única
 * barra. Bloqueia `//outro.site` e `/\outro.site` (redirecionamento aberto).
 */
export function safeRedirect(value: unknown): string {
  if (typeof value !== 'string' || !value.startsWith('/') || /^\/[/\\]/.test(value)) {
    return DEFAULT_AFTER_LOGIN
  }

  return value
}
