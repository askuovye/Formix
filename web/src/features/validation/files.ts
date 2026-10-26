import { validation } from '@/strings'
import type { FileAccept, FileField } from '@/types/schema'

/**
 * Anexos: espelho do FileAnswersValidator (back-end). O servidor confere o tipo
 * pelo conteúdo; aqui só dá para olhar a extensão e o tipo informado pelo
 * navegador, o bastante para avisar antes de enviar.
 */

const BYTES_PER_MB = 1024 * 1024

/** Soma máxima dos arquivos de um envio (padrão da instância, `submissions.max_total_upload_mb`). */
export const MAX_TOTAL_UPLOAD_MB = 25

/** Extensões e MIME aceitos por categoria (FileCategory no back-end). */
export const FILE_CATEGORIES: Record<FileAccept, { extensions: string[]; mimeTypes: string[] }> = {
  pdf: { extensions: ['pdf'], mimeTypes: ['application/pdf'] },
  image: {
    extensions: ['jpg', 'jpeg', 'png', 'webp'],
    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
  },
  document: {
    extensions: ['doc', 'docx', 'odt'],
    mimeTypes: [
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.oasis.opendocument.text',
    ],
  },
  spreadsheet: {
    extensions: ['xls', 'xlsx', 'ods', 'csv'],
    mimeTypes: [
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.oasis.opendocument.spreadsheet',
      'text/csv',
    ],
  },
  text: { extensions: ['txt'], mimeTypes: ['text/plain'] },
}

/** Valor do atributo `accept` do input (extensões e MIME, para o seletor do sistema). */
export function acceptAttribute(field: FileField): string {
  return field.file.accept
    .flatMap((category) => [
      ...FILE_CATEGORIES[category].extensions.map((extension) => `.${extension}`),
      ...FILE_CATEGORIES[category].mimeTypes,
    ])
    .join(',')
}

export function acceptedTypesLabel(field: FileField): string {
  return field.file.accept.map((category) => validation.files.categories[category]).join(', ')
}

function extensionOf(name: string): string {
  const dot = name.lastIndexOf('.')
  return dot < 0 ? '' : name.slice(dot + 1).toLowerCase()
}

/** Tipo aceito pela extensão ou, sem ela, pelo tipo que o navegador informa. */
export function isAcceptedType(field: FileField, file: Pick<File, 'name' | 'type'>): boolean {
  const extension = extensionOf(file.name)
  const categories = field.file.accept.map((category) => FILE_CATEGORIES[category])

  return extension !== ''
    ? categories.some((category) => category.extensions.includes(extension))
    : categories.some((category) => category.mimeTypes.includes(file.type))
}

/** Motivo para recusar um arquivo novo no campo (já com `count` arquivos), ou `null`. */
export function fileRejection(
  field: FileField,
  file: Pick<File, 'name' | 'type' | 'size'>,
  count: number,
): string | null {
  if (count >= field.file.max_files) return validation.files.tooMany(field.file.max_files)
  if (!isAcceptedType(field, file)) {
    return validation.files.wrongType(file.name, acceptedTypesLabel(field))
  }
  if (file.size > field.file.max_size_mb * BYTES_PER_MB) {
    return validation.files.tooLarge(file.name, field.file.max_size_mb)
  }

  return null
}

/** Erro do campo inteiro ao avançar/enviar (obrigatório e limites), ou `null`. */
export function filesError(field: FileField, files: readonly File[]): string | null {
  if (files.length === 0) return field.required ? validation.required : null
  if (files.length > field.file.max_files) return validation.files.tooMany(field.file.max_files)

  for (const file of files) {
    const rejection = fileRejection(field, file, 0)
    if (rejection) return rejection
  }

  return null
}

export function totalTooLarge(files: readonly File[]): boolean {
  return files.reduce((sum, file) => sum + file.size, 0) > MAX_TOTAL_UPLOAD_MB * BYTES_PER_MB
}

/** Tamanho legível em pt-BR (ex.: "820 KB", "1,2 MB"). */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < BYTES_PER_MB) return `${Math.round(bytes / 1024)} KB`

  return `${(bytes / BYTES_PER_MB).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`
}
