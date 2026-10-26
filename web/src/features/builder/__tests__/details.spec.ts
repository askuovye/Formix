import { describe, expect, it } from 'vitest'

import type { FormSettings } from '@/types/api'
import type { SchemaItem } from '@/types/schema'

import {
  type FormDetails,
  detailsPayload,
  detailsServerErrors,
  fromLocalInput,
  toLocalInput,
  validateDetails,
} from '../details'

const settings: FormSettings = {
  notification_emails: [],
  success_message: null,
  max_submissions: null,
  opens_at: null,
  closes_at: null,
  send_copy_to_respondent: false,
  respondent_email_field: null,
  allow_respondent_edit: false,
  retention_days: null,
}

function details(overrides: Partial<FormDetails> = {}, s: Partial<FormSettings> = {}) {
  return {
    title: 'Inscrição',
    description: '',
    slug: 'inscricao-a1b2c3',
    ...overrides,
    settings: { ...settings, ...s },
  }
}

const emailField: SchemaItem = { id: 'contato', kind: 'input', type: 'email', label: 'E-mail' }

describe('validateDetails (espelho do back-end)', () => {
  it('dados válidos não têm erro', () => {
    expect(validateDetails(details(), [], true)).toEqual({})
  })

  it.each<[string, FormDetails, Record<string, string>]>([
    ['título vazio', details({ title: ' ' }), { title: 'Preencha este campo.' }],
    [
      'endereço inválido',
      details({ slug: '-ab' }),
      {
        slug: 'Use de 3 a 80 letras minúsculas, números e hífens (sem hífen no início ou no fim).',
      },
    ],
    [
      'e-mail inválido',
      details({}, { notification_emails: ['ok@exemplo.com', 'nada'] }),
      { 'settings.notification_emails': 'Informe um e-mail válido.' },
    ],
    [
      'e-mail repetido (ignorando maiúsculas)',
      details({}, { notification_emails: ['a@exemplo.com', 'A@exemplo.com'] }),
      { 'settings.notification_emails': 'Este e-mail já está na lista.' },
    ],
    [
      'limites numéricos',
      details({}, { max_submissions: 0, retention_days: 4000 }),
      {
        'settings.max_submissions': 'Use um número inteiro de 1 a 1000000.',
        'settings.retention_days': 'Use um número inteiro de 1 a 3650.',
      },
    ],
    [
      'fechamento antes da abertura',
      details({}, { opens_at: '2026-11-02T12:00:00Z', closes_at: '2026-11-01T12:00:00Z' }),
      { 'settings.closes_at': 'O fechamento deve ser depois da abertura.' },
    ],
    [
      'cópia sem campo de e-mail escolhido',
      details({}, { send_copy_to_respondent: true, respondent_email_field: 'nome' }),
      { 'settings.respondent_email_field': 'Escolha o campo de e-mail.' },
    ],
  ])('%s', (_name, input, expected) => {
    expect(validateDetails(input, [emailField], true)).toEqual(expected)
  })

  it('cópia com campo de e-mail do formulário é válida', () => {
    const input = details({}, { send_copy_to_respondent: true, respondent_email_field: 'contato' })

    expect(validateDetails(input, [emailField], true)).toEqual({})
  })

  it('endereço travado (já publicado) não é conferido nem enviado', () => {
    const input = details({ slug: 'X' })

    expect(validateDetails(input, [], false)).toEqual({})
    expect(detailsPayload(input, false)).not.toHaveProperty('slug')
    expect(detailsPayload(input, true)).toHaveProperty('slug', 'X')
  })

  it('descrição vazia vai como null', () => {
    expect(detailsPayload(details({ description: '  ' }), false).description).toBeNull()
  })
})

describe('detailsServerErrors', () => {
  it('ignora o schema e agrupa os e-mails da lista', () => {
    expect(
      detailsServerErrors({
        'schema.fields.0.label': ['Obrigatório.'],
        schema: ['Adicione pelo menos um campo antes de publicar.'],
        'settings.notification_emails.1': ['Email inválido.'],
        'settings.notification_emails.3': ['Outro.'],
        slug: ['Já em uso.'],
      }),
    ).toEqual({ 'settings.notification_emails': 'Email inválido.', slug: 'Já em uso.' })
  })
})

describe('datas do datetime-local', () => {
  it('vai e volta no fuso do navegador', () => {
    const iso = fromLocalInput('2026-11-03T14:30')

    expect(iso).not.toBeNull()
    expect(toLocalInput(iso)).toBe('2026-11-03T14:30')
  })

  it('vazio ou inválido vira null / vazio', () => {
    expect(fromLocalInput('')).toBeNull()
    expect(toLocalInput(null)).toBe('')
    expect(toLocalInput('não é data')).toBe('')
  })
})
