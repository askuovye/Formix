import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as legalApi from '@/api/legal'
import { ApiError } from '@/api/client'

import LegalView from '../legal/LegalView.vue'

vi.mock('@/api/legal')

function mountView() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return mount(LegalView, {
    props: { document: 'privacy' },
    global: { plugins: [[VueQueryPlugin, { queryClient }]] },
  })
}

describe('LegalView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('mostra o HTML da API e a versão com a data por extenso', async () => {
    vi.mocked(legalApi.fetchLegalDocument).mockResolvedValue({
      document: 'privacy',
      title: 'Política de Privacidade',
      version: '1.0',
      updated_at: '2026-10-23',
      content_html: '<h1>Política de Privacidade</h1><p>Texto.</p>',
    })
    const wrapper = mountView()

    expect(wrapper.text()).toContain('Carregando documento…')
    await flushPromises()

    expect(legalApi.fetchLegalDocument).toHaveBeenCalledWith('privacy')
    expect(wrapper.get('.prose h1').text()).toBe('Política de Privacidade')
    expect(wrapper.text()).toContain('Versão 1.0 · atualizada em 23 de outubro de 2026')
  })

  it('erro: mostra aviso e tenta de novo', async () => {
    vi.mocked(legalApi.fetchLegalDocument).mockRejectedValueOnce(new ApiError('Falha.', 0))
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Não foi possível carregar')

    vi.mocked(legalApi.fetchLegalDocument).mockResolvedValue({
      document: 'privacy',
      title: 'Política',
      version: '1.0',
      updated_at: '2026-10-23',
      content_html: '<p>Ok.</p>',
    })
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(wrapper.get('.prose').text()).toBe('Ok.')
  })
})
