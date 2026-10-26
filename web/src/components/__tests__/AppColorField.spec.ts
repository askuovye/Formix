import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import AppColorField from '../AppColorField.vue'

function mountField(modelValue = '#111827') {
  return mount(AppColorField, { props: { label: 'Cor principal', modelValue } })
}

describe('AppColorField', () => {
  it('liga o label ao código e mostra a cor atual nos dois controles', () => {
    const wrapper = mountField()
    const code = wrapper.get('.color-field__code')

    expect(wrapper.get('label').attributes('for')).toBe(code.attributes('id'))
    expect((code.element as HTMLInputElement).value).toBe('#111827')
    expect(wrapper.get('input[type="color"]').attributes('aria-label')).toContain('cor principal')
  })

  it('código completo (com ou sem #) atualiza em maiúsculas; incompleto espera', async () => {
    const wrapper = mountField()
    const code = wrapper.get('.color-field__code')

    await code.setValue('#4f4')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    await code.setValue('4f46e5')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['#4F46E5'])
  })

  it('ao sair com código inválido, volta ao valor atual', async () => {
    const wrapper = mountField()
    const code = wrapper.get('.color-field__code')

    await code.setValue('azul')
    await code.trigger('blur')

    expect((code.element as HTMLInputElement).value).toBe('#111827')
  })

  it('o seletor nativo emite a cor escolhida', async () => {
    const wrapper = mountField()

    await wrapper.get('input[type="color"]').setValue('#ff0000')

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['#FF0000'])
  })
})
