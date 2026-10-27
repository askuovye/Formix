import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Plugin } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import * as formsApi from '@/api/forms'
import { useToastStore } from '@/stores/useToastStore'
import type { Form, FormSummary } from '@/types/api'

import CreateFormDialog from '../CreateFormDialog.vue'
import FormListItem from '../FormListItem.vue'
import FormsList from '../FormsList.vue'
import { relativeTime } from '../relativeTime'

vi.mock('@/api/forms')

const NOW = Date.parse('2026-10-26T12:00:00Z')

function lastOf<T>(list: T[]): T | undefined {
  return list[list.length - 1]
}

function summary(overrides: Partial<FormSummary> = {}): FormSummary {
  return {
    id: '01JB0000000000000000000001',
    title: 'Pesquisa de satisfação',
    slug: 'pesquisa-a1b2c3',
    status: 'published',
    published_version: 1,
    has_unpublished_changes: false,
    submissions_count: 12,
    last_submission_at: '2026-10-23T12:00:00Z',
    public_url: 'http://localhost:5173/f/pesquisa-a1b2c3',
    published_at: '2026-10-20T12:00:00Z',
    closed_at: null,
    created_at: '2026-10-20T12:00:00Z',
    updated_at: '2026-10-25T12:00:00Z',
    ...overrides,
  }
}

function page(data: FormSummary[], next: string | null = null) {
  return { data, meta: { per_page: 20, next_cursor: next, prev_cursor: null } }
}

function setup() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'forms', component: { template: '<div />' } },
      { path: '/f/:id', name: 'form-builder', component: { template: '<div />' } },
    ],
  })
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })

  const plugins: (Plugin | [Plugin, ...unknown[]])[] = [router, [VueQueryPlugin, { queryClient }]]

  return { router, plugins }
}

describe('relativeTime', () => {
  it.each([
    ['2026-10-26T11:59:30Z', 'agora'],
    ['2026-10-26T11:55:00Z', 'há 5 minutos'],
    ['2026-10-26T09:00:00Z', 'há 3 horas'],
    ['2026-10-25T12:00:00Z', 'ontem'],
    ['2026-10-16T12:00:00Z', 'há 10 dias'],
    ['2026-07-26T12:00:00Z', 'há 3 meses'],
    ['2024-10-01T12:00:00Z', 'há 2 anos'],
  ])('%s → %s', (iso, expected) => {
    expect(relativeTime(iso, NOW)).toBe(expected)
  })
})

describe('FormsList', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers({ now: NOW, toFake: ['Date'] })
    setActivePinia(createPinia())
  })

  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('lista com situação, respostas e última resposta; filtra pela API', async () => {
    vi.mocked(formsApi.listForms).mockResolvedValue(page([summary()]))
    const { plugins } = setup()
    const wrapper = mount(FormsList, { props: { status: 'published' }, global: { plugins } })
    await flushPromises()

    expect(formsApi.listForms).toHaveBeenCalledWith({ status: 'published', cursor: null })
    const text = wrapper.text()
    expect(text).toContain('Pesquisa de satisfação')
    expect(text).toContain('Ativo')
    expect(text).toContain('12 respostas')
    expect(text).toContain('Última há 3 dias')
    expect(text).toContain('Editado ontem')
  })

  it('"Todos" não envia filtro e mostra o estado vazio próprio', async () => {
    vi.mocked(formsApi.listForms).mockResolvedValue(page([]))
    const { plugins } = setup()
    const wrapper = mount(FormsList, { props: { status: 'all' }, global: { plugins } })
    await flushPromises()

    expect(formsApi.listForms).toHaveBeenCalledWith({ status: undefined, cursor: null })
    expect(wrapper.text()).toContain('Nenhum formulário ainda')
  })

  it('com busca, manda o termo e mostra o vazio da busca', async () => {
    vi.mocked(formsApi.listForms).mockResolvedValue(page([]))
    const { plugins } = setup()
    const wrapper = mount(FormsList, {
      props: { status: 'all', search: 'inscrição' },
      global: { plugins },
    })
    await flushPromises()

    expect(formsApi.listForms).toHaveBeenCalledWith({
      status: undefined,
      search: 'inscrição',
      cursor: null,
    })
    expect(wrapper.text()).toContain('Nenhum formulário encontrado')
    expect(wrapper.text()).toContain('Nada com "inscrição" no título.')
    expect(wrapper.text()).not.toContain('Nenhum formulário ainda')
  })

  it('carrega a próxima página pelo cursor', async () => {
    vi.mocked(formsApi.listForms)
      .mockResolvedValueOnce(page([summary()], 'abc'))
      .mockResolvedValueOnce(page([summary({ id: '01JB0000000000000000000002', title: 'Outro' })]))
    const { plugins } = setup()
    const wrapper = mount(FormsList, { props: { status: 'all' }, global: { plugins } })
    await flushPromises()

    await wrapper.get('.forms-list__more').trigger('click')
    await flushPromises()

    expect(formsApi.listForms).toHaveBeenLastCalledWith({ status: undefined, cursor: 'abc' })
    expect(wrapper.findAllComponents(FormListItem)).toHaveLength(2)
    expect(wrapper.find('.forms-list__more').exists()).toBe(false)
  })

  it('arquivar pede confirmação, fecha o formulário e recarrega a lista', async () => {
    vi.mocked(formsApi.listForms).mockResolvedValue(page([summary()]))
    vi.mocked(formsApi.closeForm).mockResolvedValue({} as Form)
    const { plugins } = setup()
    const wrapper = mount(FormsList, {
      props: { status: 'all' },
      global: { plugins },
      attachTo: document.body,
    })
    await flushPromises()

    wrapper.getComponent(FormListItem).vm.$emit('action', 'archive')
    await flushPromises()
    expect(document.body.textContent).toContain('Arquivar formulário?')
    expect(formsApi.closeForm).not.toHaveBeenCalled()

    const confirm = [...document.querySelectorAll('button')].find(
      (button) => button.textContent?.trim() === 'Arquivar',
    )
    confirm?.click()
    await flushPromises()

    expect(vi.mocked(formsApi.closeForm).mock.calls[0]?.[0]).toBe(summary().id)
    expect(formsApi.listForms).toHaveBeenCalledTimes(2)
    expect(lastOf(useToastStore().toasts)?.title).toBe('Formulário arquivado')
    wrapper.unmount()
  })

  it('reabrir sem alterações pendentes publica direto; com alterações, confirma antes', async () => {
    vi.mocked(formsApi.listForms).mockResolvedValue(
      page([
        summary({ status: 'closed' }),
        summary({
          id: '01JB0000000000000000000002',
          status: 'closed',
          has_unpublished_changes: true,
        }),
      ]),
    )
    vi.mocked(formsApi.publishForm).mockResolvedValue({} as Form)
    const { plugins } = setup()
    const wrapper = mount(FormsList, {
      props: { status: 'closed' },
      global: { plugins },
      attachTo: document.body,
    })
    await flushPromises()
    const [clean, changed] = wrapper.findAllComponents(FormListItem)

    clean?.vm.$emit('action', 'reopen')
    await flushPromises()
    expect(formsApi.publishForm).toHaveBeenCalledOnce()

    changed?.vm.$emit('action', 'reopen')
    await flushPromises()
    expect(formsApi.publishForm).toHaveBeenCalledOnce()
    expect(document.body.textContent).toContain('Reabrir formulário?')
    wrapper.unmount()
  })

  it('erro da API na ação vira aviso', async () => {
    vi.mocked(formsApi.listForms).mockResolvedValue(page([summary({ status: 'closed' })]))
    vi.mocked(formsApi.publishForm).mockRejectedValue(
      new ApiError('Confirme seu e-mail para publicar.', 403),
    )
    const { plugins } = setup()
    const wrapper = mount(FormsList, { props: { status: 'all' }, global: { plugins } })
    await flushPromises()

    wrapper.getComponent(FormListItem).vm.$emit('action', 'reopen')
    await flushPromises()

    expect(lastOf(useToastStore().toasts)).toMatchObject({
      title: 'Confirme seu e-mail para publicar.',
      tone: 'danger',
    })
  })

  it('falha ao carregar: aviso e botão de tentar de novo', async () => {
    vi.mocked(formsApi.listForms)
      .mockRejectedValueOnce(new ApiError('Falha.', 0))
      .mockResolvedValueOnce(page([summary()]))
    const { plugins } = setup()
    const wrapper = mount(FormsList, { props: { status: 'all' }, global: { plugins } })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Não foi possível carregar')
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Pesquisa de satisfação')
  })
})

describe('FormListItem', () => {
  it.each([
    ['draft', ['Editar', 'Ver respostas', 'Exportar respostas', 'Excluir']],
    [
      'published',
      ['Editar', 'Ver respostas', 'Exportar respostas', 'Copiar link', 'Arquivar', 'Excluir'],
    ],
    ['closed', ['Editar', 'Ver respostas', 'Exportar respostas', 'Reabrir', 'Excluir']],
  ] as const)('menu de um formulário %s', async (status, labels) => {
    const { plugins } = setup()
    const wrapper = mount(FormListItem, {
      props: { form: summary({ status }) },
      global: { plugins },
      attachTo: document.body,
    })

    await wrapper.get('button').trigger('keydown', { key: 'Enter' })
    await flushPromises()

    const items = [...document.querySelectorAll('[role="menuitem"]')].map((item) =>
      item.textContent?.trim(),
    )
    expect(items).toEqual(labels)
    wrapper.unmount()
    document.body.innerHTML = ''
  })
})

describe('CreateFormDialog', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    setActivePinia(createPinia())
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('exige nome e, ao criar, abre o editor do formulário novo', async () => {
    vi.mocked(formsApi.createForm).mockResolvedValue({
      id: '01JB0000000000000000000009',
    } as Form)
    const { plugins, router } = setup()
    const wrapper = mount(CreateFormDialog, {
      props: { open: true },
      global: { plugins },
      attachTo: document.body,
    })
    await flushPromises()
    const input = document.querySelector('input') as HTMLInputElement
    const form = document.querySelector('form') as HTMLFormElement

    form.dispatchEvent(new Event('submit'))
    await flushPromises()
    expect(formsApi.createForm).not.toHaveBeenCalled()
    expect(document.body.textContent).toContain('Dê um nome ao formulário.')

    input.value = '  Inscrição  '
    input.dispatchEvent(new Event('input'))
    form.dispatchEvent(new Event('submit'))
    await flushPromises()

    expect(vi.mocked(formsApi.createForm).mock.calls[0]?.[0]).toEqual({ title: 'Inscrição' })
    expect(router.currentRoute.value.fullPath).toBe('/f/01JB0000000000000000000009')
    expect(lastOf(wrapper.emitted('update:open') ?? [])).toEqual([false])
    wrapper.unmount()
  })
})
