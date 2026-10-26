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
  /** Mouse parado na borda antes de abrir um painel lateral (evita aberturas sem querer). */
  panelOpen: 80,
  /** Espera depois que o mouse sai antes de recolher um painel lateral. */
  panelClose: 320,
  /** Dedo parado sobre um card antes de começar a arrastar (o toque rápido continua rolando a tela). */
  dragTouch: 160,
} as const

/** Molas (motion-v) para movimentos com peso físico. Sem espelho em CSS. */
export const spring = {
  /** Painel lateral deslizando: rápido no começo, assentando sem quicar. */
  panel: { type: 'spring', stiffness: 260, damping: 30, mass: 0.9 },
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

/**
 * Card levantado ao arrastar no builder. Sem espelho em CSS.
 * follow: tempo (s) para o card alcançar o cursor (o atraso gostoso);
 * tilt: graus de inclinação por px/s de velocidade, até maxTilt;
 * shadow: px de deslocamento da sombra por px/s (paralaxe), até maxShadow.
 */
export const drag = {
  follow: 0.07,
  liftScale: 1.03,
  tilt: 0.012,
  maxTilt: 7,
  shadow: 0.012,
  maxShadow: 14,
  perspective: 900,
} as const
