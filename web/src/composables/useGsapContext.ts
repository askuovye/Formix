import { gsap } from 'gsap'
import { onMounted, onBeforeUnmount, type Ref } from 'vue'

/**
 * Executa animações GSAP com escopo no elemento e as reverte ao desmontar.
 * Com prefers-reduced-motion ativo, o setup não é executado.
 */
export function useGsapContext(
  scope: Readonly<Ref<HTMLElement | null>>,
  setup: (context: gsap.Context) => void,
): void {
  let context: gsap.Context | undefined

  onMounted(() => {
    if (!scope.value || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return
    }

    context = gsap.context(setup, scope.value)
  })

  onBeforeUnmount(() => {
    context?.revert()
  })
}
