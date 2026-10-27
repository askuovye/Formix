import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import * as formsApi from '@/api/forms'
import * as submissionsApi from '@/api/submissions'
import { SEARCH_DEBOUNCE_MS } from '@/composables/useUrlSearch'
import type { Submission, SubmissionSummary } from '@/types/api'

import SubmissionsView from '../panel/SubmissionsView.vue'

vi.mock('@/api/forms')
vi.mock('@/api/submissions')

function summary(id: string, name: string): SubmissionSummary {
  return {
    id,
    form: { id: 'f1', title: 'Inscrição' },
    schema_version: 1,
    preview: [{ label: 'Nome', value: name }],
    files_count: id === 's1' ? 2 : 0,
    created_at: new Date().toISOString(),
    edited_at: id === 's1' ? new Date().toISOString() : null,
  }
}

const detail: Submission = {
  id: 's1',
  form: { id: 'f1', title: 'Inscrição', slug: 'inscricao' },
  schema_version: 1,
  answers: [
    { field_id: 'nome', label: 'Nome', value: 'Maria Lima', multiline: false, answered: true },
    {
      field_id: 'obs',
      label: 'Observações',
      value: '(não respondido)',
      multiline: true,
      answered: false,
    },
  ],
  files: [
    {
      field_id: 'doc',
      label: 'Documento',
      files: [
        { name: 'rg.pdf', size: '120 KB', url: 'https://api.test/files/1?signature=x' },
        { name: 'foto.png', size: '80 KB', url: 'https://api.test/files/2?signature=y' },
      ],
    },
  ],
  revisions: [
    {
      replaced_at: '2026-10-26T15:00:00Z',
      answers: [
        { field_id: 'nome', label: 'Nome', value: 'Maria Souza', multiline: false, answered: true },
      ],
      replaced_files: [
        { field_id: 'doc', name: 'antigo.pdf', size: 10, mime_type: 'application/pdf' },
      ],
    },
  ],
  created_at: '2026-10-26T14:00:00Z',
  edited_at: '2026-10-26T15:00:00Z',
  editable_until: null,
}

async function mountView(path = '/painel/respostas') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/painel/respostas', name: 'submissions', component: SubmissionsView },
      { path: '/painel/formularios/:id', name: 'form-builder', component: { template: '<div />' } },
    ],
  })
  await router.push(path)

  const wrapper = mount(SubmissionsView, {
    global: { plugins: [[VueQueryPlugin, { queryClient }], router] },
    attachTo: document.body,
  })
  await flushPromises()
  return { wrapper, router }
}

describe('SubmissionsView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    setActivePinia(createPinia())
    vi.mocked(formsApi.listForms).mockResolvedValue({
      data: [],
      meta: { per_page: 50, next_cursor: null, prev_cursor: null },
    })
  })

  it('lista com os filtros da URL e carrega mais pelo cursor', async () => {
    vi.mocked(submissionsApi.listSubmissions)
      .mockResolvedValueOnce({
        data: [summary('s1', 'Maria Lima')],
        meta: { per_page: 20, next_cursor: 'c2', prev_cursor: null },
      })
      .mockResolvedValueOnce({
        data: [summary('s2', 'João Lima')],
        meta: { per_page: 20, next_cursor: null, prev_cursor: null },
      })
    const { wrapper } = await mountView('/painel/respostas?form=f1&ordem=oldest')

    expect(submissionsApi.listSubmissions).toHaveBeenCalledWith(
      { form: 'f1', sort: 'oldest' },
      null,
    )
    expect(wrapper.text()).toContain('Maria Lima')
    expect(wrapper.text()).toContain('2 anexos')
    expect(wrapper.text()).toContain('Editada')

    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Carregar mais')
      ?.trigger('click')
    await flushPromises()

    expect(submissionsApi.listSubmissions).toHaveBeenLastCalledWith(
      { form: 'f1', sort: 'oldest' },
      'c2',
    )
    expect(wrapper.text()).toContain('João Lima')
    wrapper.unmount()
  })

  it('abrir uma resposta põe na URL e mostra respostas, anexos e histórico', async () => {
    vi.mocked(submissionsApi.listSubmissions).mockResolvedValue({
      data: [summary('s1', 'Maria Lima')],
      meta: { per_page: 20, next_cursor: null, prev_cursor: null },
    })
    vi.mocked(submissionsApi.fetchSubmission).mockResolvedValue(detail)
    const { wrapper, router } = await mountView()

    await wrapper.get('.submission-item').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.resposta).toBe('s1')
    const panel = wrapper.get('.submission-detail')
    expect(panel.text()).toContain('Maria Lima')
    expect(panel.text()).toContain('(não respondido)')
    expect(panel.find('a[download]').attributes('href')).toBe(
      'https://api.test/files/1?signature=x',
    )
    expect(panel.text()).toContain('Histórico (1 edição)')
    expect(panel.text()).toContain('antigo.pdf')
    wrapper.unmount()
  })

  it('imagem anexada tem prévia que amplia numa janela', async () => {
    vi.mocked(submissionsApi.listSubmissions).mockResolvedValue({
      data: [summary('s1', 'Maria Lima')],
      meta: { per_page: 20, next_cursor: null, prev_cursor: null },
    })
    vi.mocked(submissionsApi.fetchSubmission).mockResolvedValue(detail)
    const { wrapper } = await mountView('/painel/respostas?resposta=s1')

    // Só a imagem ganha prévia; o PDF fica com o ícone.
    const previews = wrapper.findAll('.submission-detail__preview')
    expect(previews).toHaveLength(1)
    expect(previews[0]?.get('img').attributes('src')).toBe('https://api.test/files/2?signature=y')

    await previews[0]?.trigger('click')
    await flushPromises()
    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog?.textContent).toContain('foto.png')
    expect(dialog?.querySelector('img')?.getAttribute('src')).toBe(
      'https://api.test/files/2?signature=y',
    )
    wrapper.unmount()
  })

  it('busca pela URL e atualiza a URL depois de uma pausa na digitação', async () => {
    vi.mocked(submissionsApi.listSubmissions).mockResolvedValue({
      data: [],
      meta: { per_page: 20, next_cursor: null, prev_cursor: null },
    })
    const { wrapper, router } = await mountView('/painel/respostas?busca=joao')

    expect(submissionsApi.listSubmissions).toHaveBeenCalledWith({ sort: 'newest', q: 'joao' }, null)
    expect(wrapper.text()).toContain('Nada encontrado para "joao".')

    const input = wrapper.get('input[type="search"]')
    expect((input.element as HTMLInputElement).value).toBe('joao')
    await input.setValue('  maria ')
    expect(router.currentRoute.value.query.busca).toBe('joao')

    await new Promise((resolve) => setTimeout(resolve, SEARCH_DEBOUNCE_MS + 50))
    await flushPromises()

    expect(router.currentRoute.value.query.busca).toBe('maria')
    expect(submissionsApi.listSubmissions).toHaveBeenLastCalledWith(
      { sort: 'newest', q: 'maria' },
      null,
    )
    wrapper.unmount()
  })

  it('baixa o PDF da resposta aberta como arquivo', async () => {
    vi.mocked(submissionsApi.listSubmissions).mockResolvedValue({
      data: [summary('s1', 'Maria Lima')],
      meta: { per_page: 20, next_cursor: null, prev_cursor: null },
    })
    vi.mocked(submissionsApi.fetchSubmission).mockResolvedValue(detail)
    const blob = new Blob(['%PDF-'], { type: 'application/pdf' })
    vi.mocked(submissionsApi.fetchSubmissionPdf).mockResolvedValue({
      blob,
      filename: 'inscricao-s1.pdf',
    })
    // jsdom não implementa URLs de Blob nem navegação por link.
    const createUrl = vi.fn(() => 'blob:pdf')
    Object.assign(URL, { createObjectURL: createUrl, revokeObjectURL: vi.fn() })
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
      this: HTMLAnchorElement,
    ) {
      expect(this.download).toBe('inscricao-s1.pdf')
      expect(this.href).toBe('blob:pdf')
    })
    const { wrapper } = await mountView('/painel/respostas?resposta=s1')

    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Baixar PDF')
      ?.trigger('click')
    await flushPromises()

    expect(submissionsApi.fetchSubmissionPdf).toHaveBeenCalledWith('s1')
    expect(createUrl).toHaveBeenCalledWith(blob)
    expect(click).toHaveBeenCalledOnce()
    click.mockRestore()
    wrapper.unmount()
  })

  it('excluir pede confirmação, apaga e fecha o detalhe', async () => {
    vi.mocked(submissionsApi.listSubmissions).mockResolvedValue({
      data: [summary('s1', 'Maria Lima')],
      meta: { per_page: 20, next_cursor: null, prev_cursor: null },
    })
    vi.mocked(submissionsApi.fetchSubmission).mockResolvedValue(detail)
    vi.mocked(submissionsApi.deleteSubmission).mockResolvedValue()
    const { wrapper, router } = await mountView('/painel/respostas?resposta=s1')

    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Excluir resposta')
      ?.trigger('click')
    await flushPromises()
    const confirm = [...document.querySelectorAll('button')].find(
      (button) => button.textContent?.trim() === 'Excluir',
    )
    confirm?.click()
    await flushPromises()

    expect(submissionsApi.deleteSubmission).toHaveBeenCalledWith('s1')
    expect(router.currentRoute.value.query.resposta).toBeUndefined()
    wrapper.unmount()
  })
})
