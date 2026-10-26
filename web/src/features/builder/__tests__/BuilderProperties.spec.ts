import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'

import type { SchemaItem } from '@/types/schema'

import BuilderProperties from '../BuilderProperties.vue'
import { useBuilderStore } from '../useBuilderStore'

function setup(item: SchemaItem, before: SchemaItem[] = []) {
  const store = useBuilderStore()
  store.load('f1', { fields: [...before, item] })
  store.select(item.id)

  const wrapper = mount(BuilderProperties, { attachTo: document.body })

  return { store, wrapper }
}

function buttonByText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`Botão "${text}" não encontrado`)
  return button
}

describe('BuilderProperties', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('aplica o nível de rigor tirando as regras anti-lixo do campo', async () => {
    const { store, wrapper } = setup({
      id: 'nome',
      kind: 'input',
      type: 'short_text',
      label: 'Nome',
      rules: [{ type: 'cpf' }, { type: 'no_only_symbols' }],
    })

    await buttonByText(wrapper, 'Médio').trigger('click')

    expect(store.items[0]).toMatchObject({ strictness: 'medio', rules: [{ type: 'cpf' }] })
    expect(wrapper.text()).toContain('As regras do nível Médio entram ao salvar.')
    expect(buttonByText(wrapper, 'Médio').attributes('aria-pressed')).toBe('true')
    wrapper.unmount()
  })

  it('limite de caracteres vazio remove a regra', async () => {
    const { store, wrapper } = setup({
      id: 'nome',
      kind: 'input',
      type: 'short_text',
      label: 'Nome',
      rules: [{ type: 'min_length', value: 5 }],
    })

    const input = wrapper.find<HTMLInputElement>('input[type="number"]')
    expect(input.element.value).toBe('5')

    await input.setValue('')

    expect(store.items[0]).toMatchObject({ rules: [] })
    wrapper.unmount()
  })

  it('acrescenta, renomeia e remove opções de escolha', async () => {
    const { store, wrapper } = setup({
      id: 'cor',
      kind: 'input',
      type: 'multiple_choice',
      label: 'Cor',
      options: [{ value: 'opcao_1', label: 'Azul' }],
    })

    await buttonByText(wrapper, 'Adicionar opção').trigger('click')
    expect(store.items[0]).toMatchObject({
      options: [
        { value: 'opcao_1', label: 'Azul' },
        { value: 'opcao_2', label: 'Opção 2' },
      ],
    })

    await wrapper.get('[aria-label=\'Remover "Azul"\']').trigger('click')
    expect(store.items[0]).toMatchObject({ options: [{ value: 'opcao_2', label: 'Opção 2' }] })

    // Com uma opção só, remover fica desabilitado.
    expect(wrapper.get('[aria-label=\'Remover "Opção 2"\']').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('mostra o erro local no campo certo', () => {
    const { wrapper } = setup({ id: 'nome', kind: 'input', type: 'short_text', label: '' })

    expect(wrapper.text()).toContain('Preencha este campo.')
    wrapper.unmount()
  })

  it('lógica: ligar cria a condição com a primeira pergunta anterior e a opção dela', async () => {
    const cor: SchemaItem = {
      id: 'cor',
      kind: 'input',
      type: 'single_choice',
      label: 'Cor',
      options: [{ value: 'azul', label: 'Azul' }],
    }
    const { store, wrapper } = setup(
      { id: 'motivo', kind: 'input', type: 'short_text', label: 'Motivo' },
      [cor],
    )

    await wrapper.get('.visibility-editor [role="switch"]').trigger('click')

    expect(store.items[1]).toMatchObject({
      visibility: { action: 'show', when: { field: 'cor', operator: 'eq', value: 'azul' } },
    })
    expect(wrapper.text()).toContain('Campo oculto não é obrigatório')
    wrapper.unmount()
  })

  it('lógica: o primeiro campo não tem pergunta anterior, então a opção fica desligada', () => {
    const { wrapper } = setup({ id: 'nome', kind: 'input', type: 'short_text', label: 'Nome' })

    const logicSwitch = wrapper.get('.visibility-editor [role="switch"]')
    expect(logicSwitch.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('coloque antes deste campo uma pergunta')
    wrapper.unmount()
  })
})
