import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Plugin } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import * as formsApi from '@/api/forms'
import { AUTOSAVE_DELAY_MS } from '@/features/builder/useAutosave'
import { useToastStore } from '@/stores/useToastStore'
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
  settings: {
    notification_emails: [],
    success_message: null,
    max_submissions: null,
    opens_at: null,
    closes_at: null,
    send_copy_to_respondent: false,
    respondent_email_field: null,
    allow_respondent_edit: false,
    retention_days: null,
  },
}

function lastToast() {
  const { toasts } = useToastStore()
  return toasts[toasts.length - 1]
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
    // Só o que mudou vai ao servidor.
    expect(payload).not.toHaveProperty('settings')
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

  it('mudar uma configuração salva só os dados do formulário, sem o schema', async () => {
    const { wrapper } = await mountView()

    const settingsButton = wrapper
      .findAll('.workspace__side--start .rail__item')
      .find((button) => button.text() === 'Configurações')
    await settingsButton?.trigger('click')

    const limit = wrapper.findAll('.workspace__side--start input[type="number"]')[0]
    await limit?.setValue('100')
    await vi.advanceTimersByTimeAsync(AUTOSAVE_DELAY_MS)
    await flushPromises()

    const [, payload] = vi.mocked(formsApi.updateForm).mock.calls[0] ?? []
    expect(payload).not.toHaveProperty('schema')
    expect(payload?.settings?.max_submissions).toBe(100)
    expect(payload?.title).toBe('Inscrição')
    wrapper.unmount()
  })

  it('publicar salva o pendente antes e mostra a nova situação', async () => {
    vi.mocked(formsApi.publishForm).mockResolvedValue({
      ...form,
      status: 'published',
      published_version: 1,
      has_unpublished_changes: false,
    })
    const { wrapper } = await mountView()

    await wrapper.get('.canvas-item__select').trigger('click')
    await wrapper.get('.workspace__side--end textarea').setValue('Nome completo')
    await wrapper.get('.publish-control__button').trigger('click')
    await flushPromises()

    expect(formsApi.updateForm).toHaveBeenCalledOnce()
    expect(formsApi.publishForm).toHaveBeenCalledWith(form.id)
    expect(vi.mocked(formsApi.updateForm).mock.invocationCallOrder[0] ?? Infinity).toBeLessThan(
      vi.mocked(formsApi.publishForm).mock.invocationCallOrder[0] ?? 0,
    )
    expect(wrapper.get('.publish-control__button').attributes('aria-label')).toBe('Publicado')
    expect(lastToast()?.title).toBe('Formulário publicado')
    wrapper.unmount()
  })

  it('rascunho com erro não é publicado', async () => {
    const { wrapper } = await mountView()

    await wrapper.get('.canvas-item__select').trigger('click')
    await wrapper.get('.workspace__side--end textarea').setValue('')
    await wrapper.get('.publish-control__button').trigger('click')
    await flushPromises()

    expect(formsApi.updateForm).not.toHaveBeenCalled()
    expect(formsApi.publishForm).not.toHaveBeenCalled()
    expect(lastToast()?.title).toBe('Corrija os itens marcados antes de publicar.')
    wrapper.unmount()
  })

  it('e-mail não confirmado (403) mostra a mensagem do servidor', async () => {
    vi.mocked(formsApi.publishForm).mockRejectedValue(
      new ApiError('Confirme seu e-mail para publicar.', 403),
    )
    const { wrapper } = await mountView()

    await wrapper.get('.publish-control__button').trigger('click')
    await flushPromises()

    expect(lastToast()).toMatchObject({
      title: 'Confirme seu e-mail para publicar.',
      tone: 'danger',
    })
    wrapper.unmount()
  })
})
