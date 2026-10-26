import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import * as publicApi from '@/api/publicForms'
import type { EditableSubmission } from '@/types/api'

import EditSubmissionView from '../public/EditSubmissionView.vue'

vi.mock('@/api/publicForms')

function editable(overrides: Partial<EditableSubmission> = {}): EditableSubmission {
  return {
    id: 'r1',
    version: 1,
    title: 'Inscrição',
    description: null,
    schema: {
      fields: [
        { id: 'nome', kind: 'input', type: 'short_text', label: 'Nome', required: true },
        { id: 'idade', kind: 'input', type: 'number', label: 'Idade' },
        {
          id: 'doc',
          kind: 'input',
          type: 'file',
          label: 'Documento',
          required: true,
          file: { accept: ['pdf'], max_files: 1, max_size_mb: 5 },
        },
      ],
    },
    answers: { nome: 'Maria Souza', idade: 32 },
    files: [{ field_id: 'doc', files: [{ name: 'rg.pdf', size: 2048 }] }],
    editable_until: '2026-10-26T20:00:00Z',
    edited_at: null,
    ...overrides,
  }
}

async function mountView(hash = '#token=segredo') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/f/:slug/respostas/:id/editar', component: { template: '<div />' } }],
  })
  await router.push(`/f/inscricao/respostas/r1/editar${hash}`)

  const wrapper = mount(EditSubmissionView, {
    props: { slug: 'inscricao', id: 'r1' },
    global: { plugins: [[VueQueryPlugin, { queryClient }], router] },
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

describe('EditSubmissionView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lê o token do fragmento e preenche com as respostas e os anexos atuais', async () => {
    vi.mocked(publicApi.fetchEditableSubmission).mockResolvedValue(editable())
    const wrapper = await mountView()

    expect(publicApi.fetchEditableSubmission).toHaveBeenCalledWith('inscricao', 'r1', 'segredo')
    expect(wrapper.text()).toContain('Editando sua resposta')
    expect((wrapper.get('[data-field="nome"] input').element as HTMLInputElement).value).toBe(
      'Maria Souza',
    )
    expect((wrapper.get('[data-field="idade"] input').element as HTMLInputElement).value).toBe('32')
    expect(wrapper.get('[data-field="doc"]').text()).toContain('rg.pdf')
    expect(wrapper.get('[data-field="doc"]').text()).toContain('Enviado')
    wrapper.unmount()
  })

  it('salva sem arquivo novo (o atual vale para o obrigatório) e mostra a confirmação', async () => {
    vi.mocked(publicApi.fetchEditableSubmission).mockResolvedValue(editable())
    vi.mocked(publicApi.updateSubmission).mockResolvedValue(
      editable({ answers: { nome: 'Maria Lima', idade: 32 }, edited_at: '2026-10-26T17:00:00Z' }),
    )
    const wrapper = await mountView()

    await wrapper.get('[data-field="nome"] input').setValue('Maria Lima')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(publicApi.updateSubmission).toHaveBeenCalledWith(
      'inscricao',
      'r1',
      'segredo',
      { answers: { nome: 'Maria Lima', idade: '32' }, files: {} },
      expect.any(Function),
    )
    expect(wrapper.text()).toContain('Resposta atualizada!')

    await wrapper.get('button').trigger('click')
    expect((wrapper.get('[data-field="nome"] input').element as HTMLInputElement).value).toBe(
      'Maria Lima',
    )
    wrapper.unmount()
  })

  it('sem token não consulta a API e mostra link inválido', async () => {
    const wrapper = await mountView('')

    expect(publicApi.fetchEditableSubmission).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Link de edição inválido')
    wrapper.unmount()
  })

  it.each([
    [404, 'Link de edição inválido'],
    [409, 'Edição indisponível'],
    [410, 'Prazo de edição encerrado'],
  ])('recusa %i mostra o motivo', async (status, title) => {
    vi.mocked(publicApi.fetchEditableSubmission).mockRejectedValue(new ApiError('x', status))
    const wrapper = await mountView()

    expect(wrapper.text()).toContain(title)
    wrapper.unmount()
  })

  it('prazo que acaba com a página aberta: o salvamento recusado mostra o motivo', async () => {
    vi.mocked(publicApi.fetchEditableSubmission).mockResolvedValue(editable())
    vi.mocked(publicApi.updateSubmission).mockRejectedValue(new ApiError('x', 410))
    const wrapper = await mountView()

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Prazo de edição encerrado')
    wrapper.unmount()
  })
})
