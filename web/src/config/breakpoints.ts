/**
 * Breakpoints em px, espelho de styles/mixins/_breakpoints.scss (o teste
 * breakpoints.spec.ts falha se divergirem). Para o raro caso em que o script
 * precisa saber o tamanho da tela; o resto é CSS com `up()`.
 */
export const BREAKPOINTS = { sm: 640, md: 768, lg: 1024, xl: 1280 } as const

/** A tela tem pelo menos a largura do breakpoint (mesma regra do mixin `up()`). */
export function isAtLeast(name: keyof typeof BREAKPOINTS): boolean {
  return window.matchMedia(`(min-width: ${BREAKPOINTS[name]}px)`).matches
}
