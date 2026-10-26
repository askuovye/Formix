import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { THEME_STORAGE_KEY, useThemeStore } from '../useThemeStore'

type ChangeListener = (event: { matches: boolean }) => void

let listeners: ChangeListener[] = []

function mockSystemTheme(prefersLight: boolean): void {
  listeners = []
  vi.stubGlobal(
    'matchMedia',
    vi.fn(() => ({
      matches: prefersLight,
      addEventListener: (_type: string, listener: ChangeListener) => listeners.push(listener),
    })),
  )
}

describe('useThemeStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    delete document.documentElement.dataset.theme
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('usa o escuro quando o sistema não prefere o claro', async () => {
    mockSystemTheme(false)
    const store = useThemeStore()
    await nextTick()

    expect(store.preference).toBe('system')
    expect(store.resolved).toBe('dark')
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('segue o sistema quando ele prefere o claro', () => {
    mockSystemTheme(true)

    expect(useThemeStore().resolved).toBe('light')
  })

  it('acompanha a troca de tema do sistema', async () => {
    mockSystemTheme(false)
    const store = useThemeStore()

    listeners.forEach((listener) => listener({ matches: true }))
    await nextTick()

    expect(store.resolved).toBe('light')
    expect(document.documentElement.dataset.theme).toBe('light')
  })

  it('a escolha manual vence o sistema e é persistida', async () => {
    mockSystemTheme(true)
    const store = useThemeStore()

    store.setPreference('dark')
    await nextTick()

    expect(store.resolved).toBe('dark')
    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('dark')
  })

  it('carrega a escolha salva', () => {
    mockSystemTheme(false)
    localStorage.setItem(THEME_STORAGE_KEY, 'light')

    expect(useThemeStore().resolved).toBe('light')
  })

  it('voltar para "sistema" apaga a escolha salva', () => {
    mockSystemTheme(false)
    localStorage.setItem(THEME_STORAGE_KEY, 'light')
    const store = useThemeStore()

    store.setPreference('system')

    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBeNull()
    expect(store.resolved).toBe('dark')
  })

  it('ignora valor inválido salvo', () => {
    mockSystemTheme(false)
    localStorage.setItem(THEME_STORAGE_KEY, 'roxo')

    expect(useThemeStore().preference).toBe('system')
  })
})
