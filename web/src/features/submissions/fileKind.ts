import type { IconName } from '@/components/icons/iconNames'

export type FileKind = 'image' | 'pdf' | 'spreadsheet' | 'document'

/**
 * Tipo do anexo pela extensão. O nome guardado já leva a extensão do tipo
 * real, detectado pelo conteúdo no servidor (FileCategory::extensionFor).
 */
export function fileKind(name: string): FileKind {
  const extension = name.slice(name.lastIndexOf('.') + 1).toLowerCase()

  if (['jpg', 'jpeg', 'png', 'webp'].includes(extension)) return 'image'
  if (extension === 'pdf') return 'pdf'
  if (['xls', 'xlsx', 'ods', 'csv'].includes(extension)) return 'spreadsheet'
  return 'document'
}

export function iconForKind(kind: FileKind): IconName {
  if (kind === 'image') return 'image'
  if (kind === 'spreadsheet') return 'file-spreadsheet'
  return 'file-text'
}
