import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'

import { useThemeStore } from '@/stores/useThemeStore'

import AppDialog from '../AppDialog.vue'
import AppDropdownMenu, { type DropdownMenuEntry } from '../AppDropdownMenu.vue'
import AppSelect from '../AppSelect.vue'
import AppTabs from '../AppTabs.vue'
import AppTooltip from '../AppTooltip.vue'
import ThemeToggle from '../ThemeToggle.vue'

// Conteúdo de overlays vai para o <body> (portal): os testes procuram lá.
const mounted: VueWrapper[] = []

function attach<T extends VueWrapper>(wrapper: T): T {
  mounted.push(wrapper)
  return wrapper
}

function inBody(selector: string): HTMLElement[] {
  return [...document.body.querySelectorAll<HTMLElement>(selector)]
}

afterEach(() => {
  mounted.splice(0).forEach((wrapper) => wrapper.unmount())
  document.body.innerHTML = ''
})

const options = [
  { value: 'text', label: 'Texto' },
  { value: 'email', label: 'E-mail' },
]

describe('AppSelect', () => {
  it('liga o label ao gatilho e mostra o rótulo da opção escolhida', () => {
    const wrapper = attach(
      mount(AppSelect, { props: { label: 'Tipo', options, modelValue: 'email' } }),
    )
    const trigger = wrapper.get('[role="combobox"]')

    expect(wrapper.get('label').attributes('for')).toBe(trigger.attributes('id'))
    expect(trigger.text()).toContain('E-mail')
  })

  it('sem valor, mostra o placeholder padrão', () => {
    const wrapper = attach(mount(AppSelect, { props: { label: 'Tipo', options } }))

    expect(wrapper.get('[role="combobox"]').text()).toContain('Selecione uma opção')
  })

  it('liga o erro ao gatilho', () => {
    const wrapper = attach(
      mount(AppSelect, { props: { label: 'Tipo', options, error: 'Escolha um tipo.' } }),
    )
    const trigger = wrapper.get('[role="combobox"]')

    expect(trigger.attributes('aria-invalid')).toBe('true')
    expect(wrapper.get(`[id="${trigger.attributes('aria-describedby')}"]`).text()).toBe(
      'Escolha um tipo.',
    )
  })
})

describe('AppTabs', () => {
  const items = [
    { value: 'field', label: 'Campo' },
    { value: 'form', label: 'Formulário' },
  ]

  it('mostra o painel da aba ativa e troca ao clicar', async () => {
    const wrapper = attach(
      mount(AppTabs, {
        props: { items, label: 'Propriedades', modelValue: 'field' },
        slots: { field: 'Painel do campo', form: 'Painel do formulário' },
      }),
    )

    expect(wrapper.get('[role="tablist"]').attributes('aria-label')).toBe('Propriedades')
    expect(wrapper.get('[role="tabpanel"]').text()).toBe('Painel do campo')

    await wrapper.findAll('[role="tab"]')[1]?.trigger('mousedown', { button: 0 })
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['form'])
  })
})

describe('AppDropdownMenu', () => {
  const items: DropdownMenuEntry[] = [
    { type: 'item', id: 'edit', label: 'Editar' },
    { type: 'separator' },
    { type: 'item', id: 'delete', label: 'Excluir', danger: true },
  ]

  async function openMenu(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('button').trigger('keydown', { key: 'Enter' })
    await flushPromises()
  }

  it('abre pelo teclado e emite o id do item escolhido', async () => {
    const wrapper = attach(
      mount(AppDropdownMenu, {
        props: { items },
        slots: { default: () => h('button', 'Ações') },
        attachTo: document.body,
      }),
    )
    await openMenu(wrapper)

    const menuItems = inBody('[role="menuitem"]')
    expect(menuItems.map((item) => item.textContent?.trim())).toEqual(['Editar', 'Excluir'])
    expect(inBody('[role="separator"]')).toHaveLength(1)

    menuItems[1]?.click()
    await flushPromises()
    expect(wrapper.emitted('select')?.[0]).toEqual(['delete'])
  })
})

describe('AppDialog', () => {
  it('com v-model:open, mostra título e descrição ligados ao dialog', async () => {
    attach(
      mount(AppDialog, {
        props: { open: true, title: 'Excluir?', description: 'Não pode ser desfeito.' },
        attachTo: document.body,
      }),
    )
    await flushPromises()

    const dialog = inBody('[role="dialog"]')[0]
    expect(dialog).toBeDefined()
    const title = document.getElementById(dialog?.getAttribute('aria-labelledby') ?? '')
    const description = document.getElementById(dialog?.getAttribute('aria-describedby') ?? '')
    expect(title?.textContent).toBe('Excluir?')
    expect(description?.textContent?.trim()).toBe('Não pode ser desfeito.')
  })

  it('o close do slot footer fecha o dialog', async () => {
    const open = ref(true)
    const Host = defineComponent(
      () => () =>
        h(
          AppDialog,
          {
            open: open.value,
            'onUpdate:open': (value: boolean) => (open.value = value),
            title: 'Título',
          },
          { footer: ({ close }: { close: () => void }) => h('button', { onClick: close }, 'OK') },
        ),
    )
    attach(mount(Host, { attachTo: document.body }))
    await flushPromises()

    inBody('button')
      .find((button) => button.textContent === 'OK')
      ?.click()
    await nextTick()
    expect(open.value).toBe(false)
  })
})

describe('AppTooltip', () => {
  it('renderiza o gatilho do slot sem alterar seu nome acessível', () => {
    const wrapper = attach(
      mount(AppTooltip, {
        props: { content: 'Copiar link' },
        slots: { default: () => h('button', { 'aria-label': 'Copiar' }) },
      }),
    )

    expect(wrapper.get('button').attributes('aria-label')).toBe('Copiar')
  })
})

describe('ThemeToggle', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('escolher "Claro" no menu muda o tema do app', async () => {
    const wrapper = attach(mount(ThemeToggle, { attachTo: document.body }))
    const store = useThemeStore()

    expect(wrapper.get('button').attributes('aria-label')).toBe('Tema')

    await wrapper.get('button').trigger('keydown', { key: 'Enter' })
    await flushPromises()

    const radios = inBody('[role="menuitemradio"]')
    expect(radios.map((item) => item.textContent?.trim())).toEqual(['Sistema', 'Claro', 'Escuro'])
    expect(radios[0]?.getAttribute('aria-checked')).toBe('true')

    radios[1]?.click()
    await flushPromises()
    expect(store.preference).toBe('light')
    expect(document.documentElement.dataset.theme).toBe('light')
  })
})
