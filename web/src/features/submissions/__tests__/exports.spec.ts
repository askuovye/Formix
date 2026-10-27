import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Plugin } from 'vue'

import { ApiError } from '@/api/client'
import * as exportsApi from '@/api/exports'
import { filenameFrom } from '@/composables/saveBlob'
import { useExportsStore } from '@/stores/useExportsStore'
import { useToastStore } from '@/stores/useToastStore'
import type { ExportJob } from '@/types/api'

import ExportDialog from '../ExportDialog.vue'
import ExportTracker from '../ExportTracker.vue'

vi.mock('@/api/exports')

function job(overrides: Partial<ExportJob> = {}): ExportJob {
  return {
    id: 'e1',
    form_id: 'f1',
    status: 'pending',
    format: 'pdf',
    from: null,
    to: null,
    search: null,
    email: null,
    rows_count: null,
    download_url: null,
    created_at: '2026-10-27T12:00:00Z',
    completed_at: null,
    expires_at: null,
    ...overrides,
  }
}

const ready = job({
  status: 'ready',
  rows_count: 12,
  download_url: 'https://api.test/exports/e1/download?signature=x',
})

function plugins(): (Plugin | [Plugin, ...unknown[]])[] {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return [[VueQueryPlugin, { queryClient }]]
}

const button = (text: string) =>
  [...document.querySelectorAll('button')].find((b) => b.textContent?.trim() === text)

describe('exportação de respostas', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    setActivePinia(createPinia())
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('pede com os filtros e o formato escolhido e oferece o download quando fica pronto', async () => {
    vi.mocked(exportsApi.requestExport).mockResolvedValue(job())
    vi.mocked(exportsApi.fetchExport).mockResolvedValue(ready)
    const wrapper = mount(ExportDialog, {
      props: {
        open: true,
        forms: [{ value: 'f1', label: 'Inscrição' }],
        from: '2026-10-20',
        periodLabel: 'Últimos 7 dias',
        search: 'joão',
      },
      global: { plugins: plugins() },
      attachTo: document.body,
    })
    await flushPromises()

    expect(document.body.textContent).toContain('Últimos 7 dias')
    expect(document.body.textContent).toContain('“joão”')
    const pdf = document.querySelector<HTMLInputElement>('input[type="radio"][value="pdf"]')
    pdf?.click()
    await flushPromises()
    button('Exportar')?.click()
    await flushPromises()

    expect(exportsApi.requestExport).toHaveBeenCalledWith('f1', {
      format: 'pdf',
      from: '2026-10-20',
      q: 'joão',
    })
    expect(document.body.textContent).toContain('Arquivo pronto')
    expect(document.body.textContent).toContain('12 respostas')
    const link = [...document.querySelectorAll('a')].find((a) =>
      a.textContent?.includes('Baixar PDF'),
    )
    expect(link?.getAttribute('href')).toBe(ready.download_url)
    // Aberta na janela: o acompanhamento não precisa avisar de novo.
    expect(useExportsStore().visibleId).toBe('e1')
    wrapper.unmount()
  })

  it('mostra no campo de formato a recusa do PDF acima do teto', async () => {
    vi.mocked(exportsApi.requestExport).mockRejectedValue(
      new ApiError('Dados inválidos.', 422, {
        format: ['O PDF aceita até 300 respostas. Filtre por período ou busca, ou exporte em CSV.'],
      }),
    )
    const wrapper = mount(ExportDialog, {
      props: { open: true, forms: [{ value: 'f1', label: 'Inscrição' }] },
      global: { plugins: plugins() },
      attachTo: document.body,
    })
    await flushPromises()

    button('Exportar')?.click()
    await flushPromises()

    expect(document.body.textContent).toContain('O PDF aceita até 300 respostas')
    expect(exportsApi.fetchExport).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('sem formulário escolhido, pede para escolher antes de exportar', async () => {
    const wrapper = mount(ExportDialog, {
      props: {
        open: true,
        forms: [
          { value: 'f1', label: 'Inscrição' },
          { value: 'f2', label: 'Pesquisa' },
        ],
      },
      global: { plugins: plugins() },
      attachTo: document.body,
    })
    await flushPromises()

    expect(document.body.textContent).toContain('Escolha um formulário')
    expect(button('Exportar')?.disabled).toBe(true)
    wrapper.unmount()
  })

  it('com a janela fechada, avisa quando a exportação termina, com o link de download', async () => {
    vi.mocked(exportsApi.fetchExport).mockResolvedValue({ ...ready, format: 'csv' })
    const wrapper = mount(ExportTracker, { global: { plugins: plugins() } })
    useExportsStore().track(job({ format: 'csv' }), 'Inscrição')
    await flushPromises()

    const toast = useToastStore().toasts[0]
    expect(toast?.title).toBe('Exportação de "Inscrição" pronta')
    expect(toast?.action).toEqual({ label: 'Baixar CSV', href: ready.download_url })
    expect(useExportsStore().tracked).toHaveLength(0)
    wrapper.unmount()
  })

  it('lê o nome do arquivo do cabeçalho de download', () => {
    expect(filenameFrom('attachment; filename="inscricao-01ABC.pdf"', 'x.pdf')).toBe(
      'inscricao-01ABC.pdf',
    )
    expect(filenameFrom(undefined, 'resposta.pdf')).toBe('resposta.pdf')
  })
})
