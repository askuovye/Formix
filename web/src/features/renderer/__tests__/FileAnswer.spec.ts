import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import type { FileField } from '@/types/schema'

import FileAnswer from '../fields/FileAnswer.vue'

const field: FileField = {
  id: 'doc',
  kind: 'input',
  type: 'file',
  label: 'Comprovante',
  required: true,
  file: { accept: ['pdf', 'image'], max_files: 2, max_size_mb: 5 },
}

async function mountField(overrides: Partial<FileField['file']> = {}) {
  const wrapper = mount(FileAnswer, {
    props: { field: { ...field, file: { ...field.file, ...overrides } }, modelValue: [] },
  })
  await vi.waitFor(() =>
    expect(wrapper.find('input[type="file"]').attributes('disabled')).toBe(undefined),
  )
  return wrapper
}

async function choose(wrapper: Awaited<ReturnType<typeof mountField>>, files: File[]) {
  const input = wrapper.get('input[type="file"]')
  Object.defineProperty(input.element, 'files', { value: files, configurable: true })
  await input.trigger('change')
  await flushPromises()
}

const pdf = (name = 'recibo.pdf') => new File(['%PDF'], name, { type: 'application/pdf' })
const png = () => new File(['png'], 'foto.png', { type: 'image/png' })

function lastModel(wrapper: Awaited<ReturnType<typeof mountField>>): File[] {
  const events = wrapper.emitted('update:modelValue') ?? []
  return (events[events.length - 1]?.[0] as File[] | undefined) ?? []
}

describe('FileAnswer', () => {
  beforeEach(() => {
    URL.createObjectURL = vi.fn(() => 'blob:miniatura')
    URL.revokeObjectURL = vi.fn()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('mostra os limites, aceita arquivos e lista com miniatura das imagens', async () => {
    const wrapper = await mountField()

    expect(wrapper.text()).toContain(
      'PDF, imagem (JPG, PNG, WebP) · até 5 MB cada · até 2 arquivos',
    )
    expect(wrapper.get('input[type="file"]').attributes('multiple')).toBeDefined()

    await choose(wrapper, [pdf(), png()])

    expect(lastModel(wrapper).map((file) => file.name)).toEqual(['recibo.pdf', 'foto.png'])
    expect(wrapper.findAll('.file-answer__item')).toHaveLength(2)
    expect(wrapper.get('img.file-answer__thumb').attributes('src')).toBe('blob:miniatura')
    // Cheio: a área de soltar some.
    expect(wrapper.find('.file-answer__drop').exists()).toBe(false)
  })

  it('recusa tipo errado e duplicado com a mensagem no campo', async () => {
    const wrapper = await mountField()

    await choose(wrapper, [new File(['x'], 'virus.exe')])
    expect(wrapper.text()).toContain('O arquivo "virus.exe" não é de um tipo aceito.')
    expect(lastModel(wrapper)).toEqual([])

    await choose(wrapper, [pdf()])
    await choose(wrapper, [pdf()])
    expect(wrapper.text()).toContain('O arquivo "recibo.pdf" já foi adicionado.')
    expect(lastModel(wrapper)).toHaveLength(1)
  })

  it('remover tira da lista e libera a miniatura', async () => {
    const wrapper = await mountField()
    await choose(wrapper, [png()])

    await wrapper.get('button[aria-label="Remover foto.png"]').trigger('click')

    expect(lastModel(wrapper)).toEqual([])
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:miniatura')
  })

  it('com limite de 1, o arquivo novo substitui o atual', async () => {
    const wrapper = await mountField({ max_files: 1 })

    await choose(wrapper, [pdf('a.pdf')])
    await choose(wrapper, [pdf('b.pdf')])

    expect(lastModel(wrapper).map((file) => file.name)).toEqual(['b.pdf'])
  })
})
