import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import AppTextarea from '../AppTextarea.vue'
import AppTextField from '../AppTextField.vue'

describe.each([
  ['AppTextField', AppTextField, 'input'],
  ['AppTextarea', AppTextarea, 'textarea'],
] as const)('%s', (_name, component, tag) => {
  it('liga o label ao controle', () => {
    const wrapper = mount(component, { props: { label: 'Nome' } })

    expect(wrapper.get('label').attributes('for')).toBe(wrapper.get(tag).attributes('id'))
  })

  it('atualiza o v-model ao digitar', async () => {
    const wrapper = mount(component, { props: { label: 'Nome', modelValue: '' } })

    await wrapper.get(tag).setValue('Ana')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['Ana'])
  })

  it('liga dica e erro por aria-describedby e marca aria-invalid', () => {
    const wrapper = mount(component, {
      props: { label: 'Nome', hint: 'Como no documento', error: 'Informe o nome.' },
    })
    const control = wrapper.get(tag)
    const ids = control.attributes('aria-describedby')?.split(' ') ?? []

    expect(ids).toHaveLength(2)
    expect(wrapper.get(`[id="${ids[0]}"]`).text()).toBe('Como no documento')
    expect(wrapper.get(`[id="${ids[1]}"]`).text()).toBe('Informe o nome.')
    expect(control.attributes('aria-invalid')).toBe('true')
  })

  it('sem dica nem erro, não tem aria-describedby nem aria-invalid', () => {
    const control = mount(component, { props: { label: 'Nome' } }).get(tag)

    expect(control.attributes('aria-describedby')).toBeUndefined()
    expect(control.attributes('aria-invalid')).toBeUndefined()
  })

  it('obrigatório: atributo required e indicação visível', () => {
    const wrapper = mount(component, { props: { label: 'Nome', required: true } })

    expect(wrapper.get(tag).attributes('required')).toBeDefined()
    expect(wrapper.get('label').text()).toContain('obrigatório')
  })
})
