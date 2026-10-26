import { distance, duration, ease } from './tokens'

/**
 * Receitas de entrada/saída para `<motion.*>` (uso: `v-bind="slideUp"`).
 * Com prefers-reduced-motion, o MotionConfig do App.vue remove os deslocamentos.
 */

export const fade = {
  initial: { opacity: 0 },
  animate: { opacity: 1, transition: { duration: duration.base, ease: ease.out } },
  exit: { opacity: 0, transition: { duration: duration.fast, ease: ease.out } },
}

export const slideUp = {
  initial: { opacity: 0, y: distance.md },
  animate: { opacity: 1, y: 0, transition: { duration: duration.base, ease: ease.out } },
  exit: { opacity: 0, y: distance.sm, transition: { duration: duration.fast, ease: ease.out } },
}

export const scaleIn = {
  initial: { opacity: 0, scale: 0.96 },
  animate: { opacity: 1, scale: 1, transition: { duration: duration.base, ease: ease.out } },
  exit: { opacity: 0, scale: 0.98, transition: { duration: duration.fast, ease: ease.out } },
}
