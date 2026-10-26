/**
 * Tokens de movimento para Motion (motion-v) e GSAP.
 * Espelho de styles/tokens/_motion.scss; o teste motion.spec.ts falha se divergirem.
 */

/** Durações em segundos (unidade do motion-v e do GSAP). */
export const duration = {
  fast: 0.12,
  base: 0.2,
  slow: 0.32,
  /** Uma volta do indicador de carregamento. */
  spin: 0.8,
} as const

/** Atrasos em ms (unidade do Reka UI). Sem espelho em CSS. */
export const delay = {
  /** Espera antes de abrir um tooltip ao passar o mouse. */
  tooltip: 400,
  /** Tempo que um aviso (toast) fica na tela. */
  toast: 5000,
} as const

/** Curvas cúbicas de Bézier `[x1, y1, x2, y2]`. */
export const ease = {
  out: [0.22, 1, 0.36, 1],
  inOut: [0.65, 0, 0.35, 1],
} as const satisfies Record<string, readonly [number, number, number, number]>

/** Deslocamentos em px usados em entradas e saídas. */
export const distance = {
  sm: 4,
  md: 8,
  lg: 16,
} as const
