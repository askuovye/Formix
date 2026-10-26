/**
 * Ícones Lucide incluídos no bundle. Para usar um ícone novo: adicione o nome
 * aqui e rode `npm run icons` (regenera lucide-subset.json).
 * Catálogo: https://lucide.dev/icons
 */
export const ICON_NAMES = [
  'check',
  'chevron-down',
  'chevron-right',
  'circle-alert',
  'circle-check',
  'copy',
  'ellipsis',
  'info',
  'loader-circle',
  'monitor',
  'moon',
  'plus',
  'search',
  'sun',
  'trash-2',
  'x',
] as const

export type IconName = (typeof ICON_NAMES)[number]
