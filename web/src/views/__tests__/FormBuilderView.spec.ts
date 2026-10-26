import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Plugin } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'

import * as formsApi from '@/api/forms'
import { AUTOSAVE_DELAY_MS } from '@/features/builder/useAutosave'
import type { Form } from '@/types/api'

import FormBuilderView from '../panel/FormBuilderView.vue'

vi.mock('@/api/forms')

const form: Form = {
  id: '01JB0000000000000000000001',
  title: 'Inscrição',
  slug: 'inscricao-a1b2c3',
  status: 'draft',
  published_version: 0,
  has_unpublished_changes: true,
  public_url: 'http://localhost:5173/f/inscricao-a1b2c3',
  published_at: null,
  closed_at: null,
  created_at: '2026-10-26T12:00:00Z',
  updated_at: '2026-10-26T12:00:00Z',
  description: null,
  schema: {
    fields: [{ id: 'nome', kind: 'input', type: 'short_text', label: 'Nome', required: true }],
  },
  settings: {},
}

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'forms', component: { template: '<div />' } },
      { path: '/f/:id', name: 'form-builder', component: FormBuilderView, props: true },
    ],
  })
  await router.push(`/f/${form.id}`)
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const plugins: (Plugin | [Plugin, ...unknown[]])[] = [router, [VueQueryPlugin, { queryClient }]]

  const wrapper = mount(FormBuilderView, {
    props: { id: form.id },
    global: { plugins },
    attachTo: document.body,
  })
  await flushPromises()

  return { wrapper, router }
}

describe('FormBuilderView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    setActivePinia(createPinia())
    vi.mocked(formsApi.fetchForm).mockResolvedValue(form)
    vi.mocked(formsApi.updateForm).mockImplementation(async (_id, payload) => ({
      ...form,
      schema: payload.schema ?? form.schema,
    }))
  })

  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('carrega o rascunho e mostra os campos no canvas', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.get('h1').text()).toBe('Inscrição')
    expect(wrapper.get('.canvas').text()).toContain('Nome')
    expect(wrapper.get('.canvas').text()).toContain('Obrigatório')
    wrapper.unmount()
  })

  it('adicionar pela paleta, editar a pergunta e salvar sozinho depois da pausa', async () => {
    const { wrapper } = await mountView()

    const addEmail = wrapper
      .findAll('.workspace__side--start .palette__item')
      .find((button) => button.text() === 'E-mail')
    await addEmail?.trigger('click')

    expect(wrapper.findAll('.canvas-item')).toHaveLength(2)
    const label = wrapper.get('.workspace__side--end textarea')
    expect((label.element as HTMLTextAreaElement).value).toBe('E-mail')
    await label.setValue('Seu e-mail de contato')

    expect(wrapper.get('.canvas').text()).toContain('Seu e-mail de contato')
    expect(wrapper.text()).toContain('Alterações não salvas')
    expect(formsApi.updateForm).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)
    await flushPromises()

    expect(formsApi.updateForm).toHaveBeenCalledOnce()
    const [, payload] = vi.mocked(formsApi.updateForm).mock.calls[0] ?? []
    expect(payload?.schema?.fields.map((item) => item.id)).toEqual(['nome', 'email_1'])
    expect(wrapper.text()).toContain('Salvo')
    wrapper.unmount()
  })

  it('desfazer pelo teclado (fora de campos de texto) remove o que foi adicionado', async () => {
    const { wrapper } = await mountView()

    await wrapper.findAll('.workspace__side--start .palette__item')[0]?.trigger('click')
    expect(wrapper.findAll('.canvas-item')).toHaveLength(2)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true }))
    await flushPromises()

    expect(wrapper.findAll('.canvas-item')).toHaveLength(1)
    wrapper.unmount()
  })

  it('remover anuncia para leitores de tela', async () => {
    const { wrapper } = await mountView()

    await wrapper.get('[aria-label="Remover"]').trigger('click')

    expect(wrapper.findAll('.canvas-item')).toHaveLength(0)
    expect(wrapper.get('.workspace__announcer').text()).toBe(
      'Nome removido. Use Desfazer para recuperar.',
    )
    expect(wrapper.text()).toContain('Comece adicionando um campo')
    wrapper.unmount()
  })
})
