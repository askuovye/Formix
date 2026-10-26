import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import AppCheckbox from '../AppCheckbox.vue'
import AppRadioGroup from '../AppRadioGroup.vue'
import AppSwitch from '../AppSwitch.vue'

const options = [
  { value: 'a', label: 'Opção A' },
  { value: 'b', label: 'Opção B', hint: 'Detalhe' },
]

describe('AppCheckbox', () => {
  it('liga o label e emite o novo valor', async () => {
    const wrapper = mount(AppCheckbox, { props: { label: 'Aceito', modelValue: false } })
    const input = wrapper.get('input')

    expect(wrapper.get('label').attributes('for')).toBe(input.attributes('id'))
    await input.setValue(true)
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
  })
})

describe('AppRadioGroup', () => {
  it('agrupa as opções num fieldset com legend e nome comum', () => {
    const wrapper = mount(AppRadioGroup, { props: { label: 'Objetivo', options } })
    const radios = wrapper.findAll('input[type="radio"]')

    expect(wrapper.get('legend').text()).toBe('Objetivo')
    expect(radios).toHaveLength(2)
    expect(radios[0]?.attributes('name')).toBe(radios[1]?.attributes('name'))
    wrapper.findAll('label').forEach((label, index) => {
      expect(label.attributes('for')).toBe(radios[index]?.attributes('id'))
    })
  })

  it('emite o valor escolhido', async () => {
    const wrapper = mount(AppRadioGroup, { props: { label: 'Objetivo', options } })

    await wrapper.findAll('input')[1]?.setValue(true)
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['b'])
  })

  it('liga o erro ao grupo', () => {
    const wrapper = mount(AppRadioGroup, {
      props: { label: 'Objetivo', options, error: 'Escolha uma opção.' },
    })
    const fieldset = wrapper.get('fieldset')

    expect(fieldset.attributes('aria-invalid')).toBe('true')
    expect(wrapper.get(`[id="${fieldset.attributes('aria-describedby')}"]`).text()).toBe(
      'Escolha uma opção.',
    )
  })
})

describe('AppSwitch', () => {
  it('é um switch acessível ligado ao label', async () => {
    const wrapper = mount(AppSwitch, { props: { label: 'Obrigatório', modelValue: false } })
    const control = wrapper.get('[role="switch"]')

    expect(wrapper.get('label').attributes('for')).toBe(control.attributes('id'))
    expect(control.attributes('aria-checked')).toBe('false')

    await control.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
  })
})
