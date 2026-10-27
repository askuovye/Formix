import { duration, ease } from '@/motion'

/**
 * Troca o visual da página inteira revelando o novo estado num círculo que
 * nasce no centro e cresce até cobrir a tela (troca de tema claro/escuro).
 *
 * Usa a View Transitions API do navegador: ele tira uma "foto" da página antes
 * e depois de `apply` e anima só a camada nova (clip-path, no compositor), sem
 * biblioteca. Sem suporte ou com movimento reduzido, `apply` roda direto.
 */
export function circleReveal(apply: () => void): void {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (reduced || typeof document.startViewTransition !== 'function') {
    apply()
    return
  }

  const root = document.documentElement
  const transition = document.startViewTransition(() => {
    // Zera as transições dos componentes (tokens em _motion.scss) enquanto o círculo cresce.
    root.dataset.themeSwitching = ''
    apply()
  })
  // Raio até o canto mais distante a partir do centro.
  const radius = Math.hypot(window.innerWidth / 2, window.innerHeight / 2)

  void transition.finished.finally(() => {
    delete root.dataset.themeSwitching
  })

  void transition.ready.then(() => {
    document.documentElement.animate(
      {
        clipPath: [`circle(0px at 50% 50%)`, `circle(${radius}px at 50% 50%)`],
      },
      {
        duration: duration.reveal * 1000,
        easing: `cubic-bezier(${ease.inOut.join(', ')})`,
        pseudoElement: '::view-transition-new(root)',
      },
    )
  })
}
