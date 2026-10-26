import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'

import type { SchemaItem, ShortTextField } from '@/types/schema'

import { applyStrictness, findRule, formatOf, parseBlocklist, setFormat, setRule } from '../rules'
import { GENERAL_ERRORS, mapServerErrors, validateItems } from '../schemaErrors'
import { useBuilderStore } from '../useBuilderStore'

function text(overrides: Partial<ShortTextField> = {}): ShortTextField {
  return { id: 'nome', kind: 'input', type: 'short_text', label: 'Nome', ...overrides }
}

describe('regras de texto', () => {
  it('liga, altera e desliga uma regra mantendo a ordem das outras', () => {
    const item = text({ rules: [{ type: 'cpf' }, { type: 'no_only_symbols' }] })

    setRule(item, 'min_length', { type: 'min_length', value: 3 })
    setRule(item, 'cpf', { type: 'cpf', message: 'CPF inválido.' })
    setRule(item, 'no_only_symbols', null)

    expect(item.rules).toEqual([
      { type: 'cpf', message: 'CPF inválido.' },
      { type: 'min_length', value: 3 },
    ])
    expect(findRule(item, 'min_length')?.value).toBe(3)
  })

  it('formato é um só: trocar CPF por CEP tira o CPF', () => {
    const item = text({ rules: [{ type: 'cpf' }, { type: 'max_length', value: 20 }] })

    setFormat(item, 'cep')
    expect(formatOf(item)).toBe('cep')
    expect(item.rules).toEqual([{ type: 'max_length', value: 20 }, { type: 'cep' }])

    setFormat(item, null)
    expect(formatOf(item)).toBeNull()
  })

  it('nível de rigor tira as regras anti-lixo atuais e marca o preset (o servidor completa)', () => {
    const item = text({
      rules: [
        { type: 'cpf' },
        { type: 'min_length', value: 5 },
        { type: 'no_repeated_chars', threshold: 0.6 },
        { type: 'blocklist', values: ['teste'] },
      ],
    })

    applyStrictness(item, 'leve')

    expect(item.rules).toEqual([{ type: 'cpf' }, { type: 'min_length', value: 5 }])
    expect(item.strictness).toBe('leve')
  })

  it('lista de bloqueio: uma por linha, sem vazias nem repetidas (ignorando maiúsculas)', () => {
    expect(parseBlocklist(' teste \n\nASDF\nasdf\n n/a ')).toEqual(['teste', 'ASDF', 'n/a'])
  })
})

describe('validateItems (espelho do back-end)', () => {
  it('item válido não tem erro', () => {
    expect(validateItems([text()]).size).toBe(0)
  })

  it.each<[string, SchemaItem, Record<string, string>]>([
    ['rótulo vazio', text({ label: '  ' }), { label: 'Preencha este campo.' }],
    [
      'limite de caracteres fora do intervalo',
      text({ rules: [{ type: 'min_length', value: 0 }] }),
      { 'rules.min_length': 'Use um número inteiro de 1 a 10000.' },
    ],
    [
      'lista de bloqueio vazia e mensagem longa',
      text({ rules: [{ type: 'blocklist', values: [], message: 'x'.repeat(201) }] }),
      {
        'rules.blocklist': 'Inclua pelo menos uma resposta na lista.',
        'rules.blocklist.message': 'Use no máximo 200 caracteres.',
      },
    ],
    [
      'opção sem rótulo',
      {
        id: 'cor',
        kind: 'input',
        type: 'single_choice',
        label: 'Cor',
        options: [{ value: 'opcao_1', label: '' }],
      },
      { 'options.0.label': 'Preencha este campo.' },
    ],
    [
      'arquivo sem tipo e com limite vazio',
      {
        id: 'doc',
        kind: 'input',
        type: 'file',
        label: 'Doc',
        file: { accept: [], max_files: Number.NaN, max_size_mb: 5 },
      },
      {
        'file.accept': 'Escolha pelo menos um tipo de arquivo.',
        'file.max_files': 'Use um número inteiro de 1 a 10.',
      },
    ],
    [
      'escala com fim antes do início',
      {
        id: 'nps',
        kind: 'input',
        type: 'scale',
        label: 'NPS',
        scale: { min: 5, max: 3, min_label: null, max_label: null },
      },
      { 'scale.max': 'O fim precisa ser maior que o início.' },
    ],
    [
      'estrelas fora do intervalo',
      { id: 'nota', kind: 'input', type: 'rating', label: 'Nota', rating: { max: 11 } },
      { 'rating.max': 'Use um número inteiro de 3 a 10.' },
    ],
  ])('%s', (_name, item, expected) => {
    expect(validateItems([item]).get(item.id)).toEqual(expected)
  })

  it('quebra de etapa no início, no fim ou repetida', () => {
    const pageBreak = (id: string): SchemaItem => ({ id, kind: 'block', type: 'page_break' })
    const errors = validateItems([
      pageBreak('etapa_1'),
      text(),
      pageBreak('etapa_2'),
      pageBreak('etapa_3'),
      text({ id: 'b' }),
      pageBreak('etapa_4'),
    ])

    expect([...errors.keys()]).toEqual(['etapa_1', 'etapa_3', 'etapa_4'])
  })
})

describe('mapServerErrors', () => {
  it('converte índices do 422 em id do item e tipo da regra', () => {
    const sent: SchemaItem[] = [
      { id: 'titulo', kind: 'block', type: 'section_title', text: 'Oi' },
      text({ rules: [{ type: 'cpf' }, { type: 'min_length', value: 99999 }] }),
      {
        id: 'cor',
        kind: 'input',
        type: 'single_choice',
        label: 'Cor',
        options: [{ value: 'a', label: 'A' }],
      },
    ]

    const result = mapServerErrors(
      {
        'schema.fields.1.rules.1.value': ['Valor alto demais.'],
        'schema.fields.1.rules.0.message': ['Mensagem longa.'],
        'schema.fields.2.options.0.value': ['Valor repetido.'],
        'schema.fields.0.type': ['Posição inválida.'],
        'schema.fields': ['Campos demais.'],
      },
      sent,
    )

    expect(result.get('nome')).toEqual({
      'rules.min_length': 'Valor alto demais.',
      'rules.cpf.message': 'Mensagem longa.',
    })
    expect(result.get('cor')).toEqual({ 'options.0.label': 'Valor repetido.' })
    expect(result.get('titulo')).toEqual({ item: 'Posição inválida.' })
    expect(result.get(GENERAL_ERRORS)).toEqual({ 'schema.fields': 'Campos demais.' })
  })
})

describe('store: erros e regras do rigor', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('junta erros do servidor e locais; mexer no item apaga os do servidor', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [text()] })
    store.setServerErrors(new Map([['nome', { label: 'Recusado pelo servidor.' }]]))

    expect(store.errorsFor('nome')).toEqual({ label: 'Recusado pelo servidor.' })

    store.update('nome', (item) => {
      if (item.kind === 'input') item.label = ''
    })

    expect(store.errorsFor('nome')).toEqual({ label: 'Preencha este campo.' })
    expect(store.hasClientErrors).toBe(true)
  })

  it('adota as regras que o servidor gerou do rigor, sem histórico nem novo salvamento', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [text()] })
    store.update('nome', (item) => {
      if (item.kind === 'input' && item.type === 'short_text') applyStrictness(item, 'leve')
    })
    const sent = store.snapshot()
    const revision = store.revision
    const saved: SchemaItem[] = [
      text({
        rules: [{ type: 'no_only_symbols' }, { type: 'no_repeated_chars', threshold: 0.9 }],
      }),
    ]

    store.adoptServerRules(sent, saved)

    const item = store.items[0]
    expect(item?.kind === 'input' && item.type === 'short_text' && item.strictness).toBeFalsy()
    expect(item?.kind === 'input' && item.type === 'short_text' && item.rules).toEqual(
      saved[0]?.kind === 'input' && saved[0].type === 'short_text' ? saved[0].rules : null,
    )
    expect(store.revision).toBe(revision)
  })

  it('snapshot funciona depois de filtrar regras (filter de array reativo devolve proxies)', () => {
    const store = useBuilderStore()
    store.load('f1', {
      fields: [text({ rules: [{ type: 'cpf' }, { type: 'no_only_symbols' }] })],
    })
    store.update('nome', (item) => {
      if (item.kind === 'input' && item.type === 'short_text') applyStrictness(item, 'medio')
    })

    expect(store.snapshot()).toEqual([text({ rules: [{ type: 'cpf' }], strictness: 'medio' })])
  })

  it('não adota se o item mudou de novo enquanto salvava', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [text()] })
    store.update('nome', (item) => {
      if (item.kind === 'input' && item.type === 'short_text') applyStrictness(item, 'leve')
    })
    const sent = store.snapshot()
    store.update('nome', (item) => {
      if (item.kind === 'input' && item.type === 'short_text') applyStrictness(item, 'estrito')
    })

    store.adoptServerRules(sent, [text({ rules: [{ type: 'no_only_symbols' }] })])

    const item = store.items[0]
    expect(item?.kind === 'input' && item.type === 'short_text' && item.strictness).toBe('estrito')
  })
})
