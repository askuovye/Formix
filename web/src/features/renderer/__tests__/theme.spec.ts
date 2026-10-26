import { describe, expect, it } from 'vitest'

import {
  DEFAULT_THEME,
  PRESET_COLORS,
  contrastOn,
  contrastRatio,
  isDark,
  resolveTheme,
  themeClasses,
  themeStyle,
} from '../theme'

describe('tema do formulário', () => {
  it('sem tema (ou com cor inválida) vale o Clássico, como no servidor', () => {
    expect(resolveTheme(undefined)).toEqual(DEFAULT_THEME)
    expect(
      resolveTheme({ font: 'serif', colors: { ...DEFAULT_THEME.colors, primary: 'azul' } }),
    ).toEqual({ ...DEFAULT_THEME, font: 'serif' })
  })

  it('texto sobre a primária: o de maior contraste', () => {
    expect(contrastOn('#111827')).toBe('#FFFFFF')
    expect(contrastOn('#F2F3F5')).toBe('#0A0B0D')
    expect(contrastOn('#4F46E5')).toBe('#FFFFFF')
    expect(contrastRatio('#FFFFFF', '#000000')).toBeCloseTo(21)
  })

  it('aplica as cores-base e os modificadores', () => {
    const theme = resolveTheme({ colors: PRESET_COLORS.dark, radius: 'large', alignment: 'center' })

    expect(themeStyle(theme)).toEqual({
      '--color-bg': '#0A0B0D',
      '--color-surface': '#111316',
      '--color-text': '#F2F3F5',
      '--color-primary': '#F2F3F5',
      '--color-primary-contrast': '#0A0B0D',
    })
    expect(themeClasses(theme)).toEqual([
      'form-theme--radius-large',
      'form-theme--font-inter',
      'form-theme--align-center',
      'form-theme--dark',
    ])
  })

  it('presets claros e escuros são reconhecidos pela luz do fundo', () => {
    expect(isDark(PRESET_COLORS.dark.background)).toBe(true)
    expect(isDark(PRESET_COLORS.classic.background)).toBe(false)
    expect(isDark(PRESET_COLORS.modern.background)).toBe(false)
  })
})
