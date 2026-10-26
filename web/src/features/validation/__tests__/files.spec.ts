import { describe, expect, it } from 'vitest'

import type { FileField } from '@/types/schema'

import {
  acceptAttribute,
  fileRejection,
  filesError,
  formatFileSize,
  isAcceptedType,
  totalTooLarge,
} from '../files'

const MB = 1024 * 1024

const field: FileField = {
  id: 'doc',
  kind: 'input',
  type: 'file',
  label: 'Comprovante',
  required: true,
  file: { accept: ['pdf', 'image'], max_files: 2, max_size_mb: 5 },
}

function file(name: string, size = 1000, type = ''): File {
  const value = new File(['x'], name, { type })
  Object.defineProperty(value, 'size', { value: size })
  return value
}

describe('files', () => {
  it('accept do input: extensões e MIME das categorias liberadas', () => {
    expect(acceptAttribute(field)).toBe(
      '.pdf,application/pdf,.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
    )
  })

  it('tipo pela extensão (sem ela, pelo MIME do navegador); nunca SVG ou executável', () => {
    expect(isAcceptedType(field, file('Recibo.PDF'))).toBe(true)
    expect(isAcceptedType(field, file('foto.jpeg'))).toBe(true)
    expect(isAcceptedType(field, file('foto.png.exe'))).toBe(false)
    expect(isAcceptedType(field, file('logo.svg', 1, 'image/svg+xml'))).toBe(false)
    expect(isAcceptedType(field, file('sem-extensao', 1, 'application/pdf'))).toBe(true)
  })

  it('recusa por quantidade, tipo e tamanho com as mensagens do servidor', () => {
    expect(fileRejection(field, file('a.pdf'), 2)).toBe('Envie no máximo 2 arquivos.')
    expect(fileRejection(field, file('a.zip'), 0)).toBe(
      'O arquivo "a.zip" não é de um tipo aceito. Envie: PDF, imagem (JPG, PNG, WebP).',
    )
    expect(fileRejection(field, file('a.pdf', 5 * MB + 1), 0)).toBe(
      'O arquivo "a.pdf" passa do limite de 5 MB.',
    )
    expect(fileRejection(field, file('a.pdf', 5 * MB), 1)).toBeNull()
  })

  it('erro do campo: obrigatório, limites; opcional vazio passa', () => {
    expect(filesError(field, [])).toBe('Este campo é obrigatório.')
    expect(filesError({ ...field, required: false }, [])).toBeNull()
    expect(filesError(field, [file('a.pdf'), file('b.pdf'), file('c.pdf')])).toBe(
      'Envie no máximo 2 arquivos.',
    )
    expect(filesError(field, [file('a.pdf')])).toBeNull()
  })

  it('soma do envio e tamanho legível', () => {
    expect(totalTooLarge([file('a.pdf', 20 * MB), file('b.pdf', 5 * MB)])).toBe(false)
    expect(totalTooLarge([file('a.pdf', 20 * MB), file('b.pdf', 5 * MB + 1)])).toBe(true)
    expect(formatFileSize(512)).toBe('512 B')
    expect(formatFileSize(820 * 1024)).toBe('820 KB')
    expect(formatFileSize(1.25 * MB)).toBe('1,3 MB')
  })
})
