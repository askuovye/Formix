import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import * as publicApi from '@/api/publicForms'
import type { PublicForm } from '@/types/api'

import PublicFormView from '../public/PublicFormView.vue'

vi.mock('@/api/publicForms')

function publicForm(overrides: Partial<PublicForm> = {}): PublicForm {
  return {
    slug: 'inscricao',
    version: 1,
    title: 'Inscrição',
    description: 'Preencha com calma.',
    accepts_submissions: true,
    availability: 'open',
    unavailable_message: null,
    schema: {
      fields: [
        { id: 'nome', kind: 'input', type: 'short_text', label: 'Nome', required: true },
        { id: 'site', kind: 'input', type: 'short_text', label: 'Site' },
      ],
    },
    opens_at: null,
    closes_at: null,
    allows_respondent_edit: false,
    privacy_policy_url: 'https://app.exemplo.com/privacidade',
    ...overrides,
  }
}

function mountView() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', component: { template: '<div />' } }],
  })

  return mount(PublicFormView, {
    props: { slug: 'inscricao' },
    global: { plugins: [[VueQueryPlugin, { queryClient }], router] },
    attachTo: document.body,
  })
}

async function typeName(wrapper: ReturnType<typeof mountView>, value: string): Promise<void> {
  await wrapper.get('[data-field="nome"] input').setValue(value)
}

describe('PublicFormView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('mostra o formulário; com erro não envia e foca o campo', async () => {
    vi.mocked(publicApi.fetchPublicForm).mockResolvedValue(publicForm())
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Inscrição')
    expect(wrapper.text()).toContain('Política de Privacidade')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(publicApi.submitAnswers).not.toHaveBeenCalled()
    expect(wrapper.get('[data-field="nome"]').text()).toContain('Este campo é obrigatório.')
    expect(document.activeElement).toBe(wrapper.get('[data-field="nome"] input').element)
    wrapper.unmount()
  })

  it('envia as respostas normalizadas, com honeypot e chave de idempotência, e mostra o comprovante', async () => {
    vi.mocked(publicApi.fetchPublicForm).mockResolvedValue(publicForm())
    vi.mocked(publicApi.submitAnswers).mockResolvedValue({
      id: 'r1',
      success_message: 'Recebemos sua inscrição.',
      edit_url: null,
      editable_until: null,
    })
    const wrapper = mountView()
    await flushPromises()

    await typeName(wrapper, '  Maria  Souza ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(publicApi.submitAnswers).toHaveBeenCalledWith(
      'inscricao',
      { answers: { nome: 'Maria Souza', site: null }, files: {}, website: '' },
      expect.stringMatching(/^[0-9a-f-]{36}$/),
      expect.any(Function),
    )
    expect(wrapper.text()).toContain('Recebemos sua inscrição.')
    wrapper.unmount()
  })

  it('erro 422 do servidor aparece no campo; erro sem campo aparece no topo do envio', async () => {
    vi.mocked(publicApi.fetchPublicForm).mockResolvedValue(publicForm())
    vi.mocked(publicApi.submitAnswers).mockRejectedValue(
      new ApiError('Dados inválidos.', 422, {
        nome: ['O campo Nome não parece válido.'],
        captcha: ['Confirme que você não é um robô.'],
      }),
    )
    const wrapper = mountView()
    await flushPromises()

    await typeName(wrapper, 'Maria')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-field="nome"]').text()).toContain('O campo Nome não parece válido.')
    expect(wrapper.get('[role="alert"]').text()).toContain('Confirme que você não é um robô.')
    wrapper.unmount()
  })

  it('409 ao enviar: recarrega e mostra que o formulário fechou', async () => {
    vi.mocked(publicApi.fetchPublicForm)
      .mockResolvedValueOnce(publicForm())
      .mockResolvedValue(
        publicForm({
          accepts_submissions: false,
          availability: 'limit_reached',
          unavailable_message: 'Este formulário atingiu o limite de respostas.',
          schema: null,
        }),
      )
    vi.mocked(publicApi.submitAnswers).mockRejectedValue(new ApiError('Fechado.', 409))
    const wrapper = mountView()
    await flushPromises()

    await typeName(wrapper, 'Maria')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).toContain('Limite de respostas atingido')
    expect(wrapper.text()).toContain('Este formulário atingiu o limite de respostas.')
    wrapper.unmount()
  })

  it('ainda não aberto: mostra quando abre', async () => {
    vi.mocked(publicApi.fetchPublicForm).mockResolvedValue(
      publicForm({
        accepts_submissions: false,
        availability: 'not_open_yet',
        unavailable_message: 'Este formulário ainda não está aberto para respostas.',
        schema: null,
        opens_at: '2026-12-01T12:00:00Z',
      }),
    )
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Ainda não começou')
    expect(wrapper.text()).toContain('Abre em 1 de dezembro de 2026')
    wrapper.unmount()
  })

  it('slug inexistente: formulário não encontrado', async () => {
    vi.mocked(publicApi.fetchPublicForm).mockRejectedValue(new ApiError('Não encontrado.', 404))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('Formulário não encontrado')
    wrapper.unmount()
  })
})
