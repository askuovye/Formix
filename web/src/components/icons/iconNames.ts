/**
 * Ícones Lucide incluídos no bundle. Para usar um ícone novo: adicione o nome
 * aqui e rode `npm run icons` (regenera lucide-subset.json).
 * Catálogo: https://lucide.dev/icons
 */
export const ICON_NAMES = [
  'archive',
  'arrow-left',
  'check',
  'chevron-down',
  'chevron-right',
  'circle-alert',
  'circle-check',
  'copy',
  'ellipsis',
  'eye',
  'file-text',
  'info',
  'loader-circle',
  'log-out',
  'mail',
  'menu',
  'monitor',
  'moon',
  'pencil',
  'plus',
  'rotate-ccw',
  'search',
  'settings',
  'sun',
  'trash-2',
  'x',
] as const

export type IconName = (typeof ICON_NAMES)[number]
