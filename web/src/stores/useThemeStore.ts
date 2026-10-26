import { defineStore } from 'pinia'
import { computed, ref, watchEffect } from 'vue'

export type ThemePreference = 'system' | 'light' | 'dark'
export type ResolvedTheme = Exclude<ThemePreference, 'system'>

/** Mesma chave lida pelo script inline do index.html (manter as duas iguais). */
export const THEME_STORAGE_KEY = 'formix:theme'

const LIGHT_QUERY = '(prefers-color-scheme: light)'

function readStoredPreference(): ThemePreference {
  try {
    const stored = localStorage.getItem(THEME_STORAGE_KEY)

    return stored === 'light' || stored === 'dark' ? stored : 'system'
  } catch {
    return 'system'
  }
}

function writeStoredPreference(preference: ThemePreference): void {
  try {
    if (preference === 'system') {
      localStorage.removeItem(THEME_STORAGE_KEY)
    } else {
      localStorage.setItem(THEME_STORAGE_KEY, preference)
    }
  } catch {
    // Armazenamento indisponível (aba privada, bloqueio): vale só nesta sessão.
  }
}

/** Tema do app (claro/escuro). O tema do formulário é independente e vive no renderer. */
export const useThemeStore = defineStore('theme', () => {
  const preference = ref<ThemePreference>(readStoredPreference())

  const media = window.matchMedia(LIGHT_QUERY)
  const systemPrefersLight = ref(media.matches)
  media.addEventListener('change', (event) => {
    systemPrefersLight.value = event.matches
  })

  // Sem preferência do sistema, o padrão é o escuro.
  const resolved = computed<ResolvedTheme>(() => {
    if (preference.value !== 'system') {
      return preference.value
    }

    return systemPrefersLight.value ? 'light' : 'dark'
  })

  watchEffect(() => {
    document.documentElement.dataset.theme = resolved.value
  })

  function setPreference(next: ThemePreference): void {
    preference.value = next
    writeStoredPreference(next)
  }

  return { preference, resolved, setPreference }
})
