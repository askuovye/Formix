import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { SCHEMA_LIMITS, type SchemaItem } from '@/types/schema'

import { PALETTE, nextId, paletteEntryFor } from '../catalog'
import { useBuilderStore } from '../useBuilderStore'

function ids(store: ReturnType<typeof useBuilderStore>): string[] {
  return store.items.map((item) => item.id)
}

function label(item: SchemaItem | undefined): string | undefined {
  return item?.kind === 'input' ? item.label : undefined
}

describe('catálogo', () => {
  it('cada tipo da paleta cria um item válido para o contrato, com id no padrão', () => {
    for (const entry of PALETTE) {
      const item = entry.create(nextId(entry.idPrefix, new Set()))

      expect(item.id).toMatch(SCHEMA_LIMITS.idPattern)
      expect(paletteEntryFor(item).key).toBe(entry.key)
    }
  })

  it('o próximo id pula os já usados', () => {
    expect(nextId('texto', new Set(['texto_1', 'texto_2']))).toBe('texto_3')
  })
})

describe('useBuilderStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('carrega o schema; itens antigos sem `kind` viram campos e o tema é preservado', () => {
    const store = useBuilderStore()
    store.load('f1', {
      fields: [{ id: 'nome', type: 'short_text', label: 'Nome' } as SchemaItem],
      theme: { preset: 'classico' },
    })

    expect(store.items[0]?.kind).toBe('input')
    expect(store.schema.theme).toEqual({ preset: 'classico' })
    expect(store.revision).toBe(0)
    expect(store.canUndo).toBe(false)
  })

  it('adiciona no fim, ou logo após o selecionado, e seleciona o novo', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [] })

    store.add('short_text')
    store.add('email')
    store.select('texto_1')
    store.add('cpf')

    expect(ids(store)).toEqual(['texto_1', 'cpf_1', 'email_1'])
    expect(store.selectedId).toBe('cpf_1')
    expect(store.revision).toBe(3)
  })

  it('item vindo do arrastar com id já usado ganha um id livre', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [] })
    const dragged = store.createItem('short_text')
    store.add('short_text')

    store.insert(dragged, 0)

    expect(ids(store)).toEqual(['texto_2', 'texto_1'])
  })

  it('move, duplica (com id novo) e remove selecionando o vizinho', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [] })
    store.add('short_text')
    store.add('number')
    store.add('date')

    store.move(2, 0)
    expect(ids(store)).toEqual(['data_1', 'texto_1', 'numero_1'])

    store.duplicate('texto_1')
    expect(ids(store)).toEqual(['data_1', 'texto_1', 'texto_2', 'numero_1'])
    expect(store.selectedId).toBe('texto_2')

    store.remove('texto_2')
    expect(ids(store)).toEqual(['data_1', 'texto_1', 'numero_1'])
    expect(store.selectedId).toBe('numero_1')
  })

  it('desfaz e refaz; uma ação nova descarta o refazer', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [] })
    store.add('short_text')
    store.add('email')

    store.undo()
    expect(ids(store)).toEqual(['texto_1'])
    store.undo()
    expect(ids(store)).toEqual([])
    expect(store.canUndo).toBe(false)

    store.redo()
    expect(ids(store)).toEqual(['texto_1'])

    store.add('date')
    expect(store.canRedo).toBe(false)
  })

  it('digitação seguida no mesmo campo vira um passo só de desfazer', () => {
    vi.useFakeTimers()
    const store = useBuilderStore()
    store.load('f1', { fields: [] })
    store.add('short_text')
    const rename = (value: string) =>
      store.update(
        'texto_1',
        (item) => {
          if (item.kind === 'input') item.label = value
        },
        'label',
      )

    rename('N')
    rename('No')
    rename('Nome')
    vi.advanceTimersByTime(2000)
    rename('Nome completo')

    store.undo()
    expect(label(store.items[0])).toBe('Nome')
    store.undo()
    expect(label(store.items[0])).toBe('Pergunta sem título')
    vi.useRealTimers()
  })

  it('desfazer não deixa a seleção apontando para item que sumiu', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [] })
    store.add('short_text')

    store.undo()

    expect(store.selectedId).toBeNull()
  })

  it('o histórico guarda cópias: mudar o item depois não altera o passo anterior', () => {
    const store = useBuilderStore()
    store.load('f1', { fields: [] })
    store.add('short_text')
    store.update('texto_1', (item) => {
      if (item.kind === 'input') item.required = true
    })

    store.undo()

    expect(store.items[0]?.kind === 'input' && store.items[0].required).toBe(false)
  })
})
