import type { FormTheme, ThemePreset } from '@/types/schema'

/**
 * Tema do formulário: cores do dono viram custom properties no escopo do
 * renderer (o resto dos papéis é derivado pelo mixin color-roles).
 */

/** Espelho de FormTheme::DEFAULTS (back-end): o "Clássico". */
export const DEFAULT_THEME: FormTheme = {
  preset: 'classic',
  colors: { primary: '#111827', background: '#F6F7F9', surface: '#FFFFFF', text: '#111827' },
  radius: 'medium',
  font: 'inter',
  button_style: 'solid',
  alignment: 'left',
  cover: null,
  logo: null,
}

/** Paletas dos presets (o Personalizado parte das cores atuais). */
export const PRESET_COLORS: Record<Exclude<ThemePreset, 'custom'>, FormTheme['colors']> = {
  classic: DEFAULT_THEME.colors,
  minimal: { primary: '#18181B', background: '#FFFFFF', surface: '#FFFFFF', text: '#18181B' },
  dark: { primary: '#F2F3F5', background: '#0A0B0D', surface: '#111316', text: '#F2F3F5' },
  modern: { primary: '#4F46E5', background: '#EEF0FB', surface: '#FFFFFF', text: '#1E1B4B' },
}

const HEX = /^#[0-9a-fA-F]{6}$/

/** Completa um tema parcial (ou ausente) com o Clássico, como o servidor faz ao salvar. */
export function resolveTheme(theme: Partial<FormTheme> | null | undefined): FormTheme {
  const colors = { ...DEFAULT_THEME.colors }
  for (const key of Object.keys(colors) as (keyof FormTheme['colors'])[]) {
    const value = theme?.colors?.[key]
    if (typeof value === 'string' && HEX.test(value)) colors[key] = value
  }

  return { ...DEFAULT_THEME, ...theme, colors }
}

function channels(hex: string): [number, number, number] {
  const value = Number.parseInt(hex.slice(1), 16)
  return [(value >> 16) & 255, (value >> 8) & 255, value & 255]
}

/** Luminância relativa (WCAG 2.x). */
export function luminance(hex: string): number {
  const [r, g, b] = channels(hex).map((channel) => {
    const c = channel / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  }) as [number, number, number]

  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

export function contrastRatio(a: string, b: string): number {
  const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x) as [number, number]
  return (light + 0.05) / (dark + 0.05)
}

/** Fundo escuro pede as variantes claras das cores de estado e controles nativos escuros. */
export function isDark(hex: string): boolean {
  return luminance(hex) < 0.18
}

const WHITE = '#FFFFFF'
const NEAR_BLACK = '#0A0B0D'

/** Texto sobre a cor primária: branco ou quase preto, o que tiver mais contraste. */
export function contrastOn(hex: string): string {
  return contrastRatio(hex, WHITE) >= contrastRatio(hex, NEAR_BLACK) ? WHITE : NEAR_BLACK
}

/** Custom properties do tema, aplicadas no elemento raiz do formulário. */
export function themeStyle(theme: FormTheme): Record<string, string> {
  const { primary, background, surface, text } = theme.colors

  return {
    '--color-bg': background,
    '--color-surface': surface,
    '--color-text': text,
    '--color-primary': primary,
    '--color-primary-contrast': contrastOn(primary),
  }
}

/** Classes BEM do escopo do tema (modificadores de raio, fonte, botão, alinhamento e luz). */
export function themeClasses(theme: FormTheme): string[] {
  return [
    `form-theme--radius-${theme.radius}`,
    `form-theme--font-${theme.font}`,
    `form-theme--align-${theme.alignment}`,
    isDark(theme.colors.background) ? 'form-theme--dark' : 'form-theme--light',
  ]
}
