import { describe, expect, it } from 'vitest'

import { fileKind, iconForKind } from '../fileKind'

describe('tipo do anexo', () => {
  it('reconhece pela extensão, sem diferenciar maiúsculas', () => {
    expect(fileKind('foto.JPG')).toBe('image')
    expect(fileKind('perfil.webp')).toBe('image')
    expect(fileKind('rg.pdf')).toBe('pdf')
    expect(fileKind('notas.xlsx')).toBe('spreadsheet')
    expect(fileKind('carta.docx')).toBe('document')
    expect(fileKind('sem-extensao')).toBe('document')
  })

  it('escolhe o ícone do tipo', () => {
    expect(iconForKind('image')).toBe('image')
    expect(iconForKind('spreadsheet')).toBe('file-spreadsheet')
    expect(iconForKind('pdf')).toBe('file-text')
  })
})
