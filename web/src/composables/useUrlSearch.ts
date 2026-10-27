import { computed, onBeforeUnmount, ref, watch, type ComputedRef, type Ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/** Espera depois da última tecla antes de buscar no servidor. */
export const SEARCH_DEBOUNCE_MS = 300

/**
 * Busca guardada na URL (`?busca=`): sobrevive ao recarregar e ao voltar.
 * `input` acompanha o que é digitado; `term` (já sem espaços nas pontas) só
 * muda depois da pausa, e é ele que vai para a chave da consulta.
 */
export function useUrlSearch(key = 'busca'): {
  input: Ref<string>
  term: ComputedRef<string>
} {
  const route = useRoute()
  const router = useRouter()

  const term = computed(() => {
    const value = route.query[key]
    return typeof value === 'string' ? value.trim() : ''
  })

  const input = ref(term.value)
  let timer: ReturnType<typeof setTimeout> | undefined

  watch(input, (value) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      const next = value.trim()
      if (next === term.value) return
      void router.replace({ query: { ...route.query, [key]: next === '' ? undefined : next } })
    }, SEARCH_DEBOUNCE_MS)
  })

  // Voltar/avançar no navegador muda a URL por fora: o campo acompanha.
  watch(term, (value) => {
    if (value !== input.value.trim()) input.value = value
  })

  onBeforeUnmount(() => clearTimeout(timer))

  return { input, term }
}
