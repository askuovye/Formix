import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import NewPasswordField from '../NewPasswordField.vue'
import { passwordStrength } from '../passwordStrength'

describe('passwordStrength', () => {
  it.each([
    ['', 0],
    // Fora das regras obrigatórias.
    ['curta1', 1],
    ['somenteletras', 1],
    ['1234567890', 1],
    // Dentro das regras, mas previsível.
    ['aaaaaaa1', 1],
    ['12345678a', 1],
    ['abcdefgh1', 1],
    ['minhasenha1', 1],
    ['Qwerty2026', 1],
    // Só o mínimo.
    ['casa7lua', 2],
    // Mais longa ou com maiúsculas e minúsculas.
    ['casa7lua9mar', 3],
    ['Casa7lua', 3],
    // Longa, variada e com símbolo.
    ['Casa7lua!mar9', 4],
    ['viagem 7 para o mar azul!', 4],
  ])('%j → %i', (password, expected) => {
    expect(passwordStrength(password)).toBe(expected)
  })
})

describe('NewPasswordField', () => {
  it('mostra a barra e o nível conforme digita', async () => {
    const wrapper = mount(NewPasswordField, {
      props: {
        modelValue: '',
        label: 'Senha',
        'onUpdate:modelValue': (value: string) => wrapper.setProps({ modelValue: value }),
      },
    })

    expect(wrapper.find('.strength__segment--on').exists()).toBe(false)
    expect(wrapper.get('[aria-live="polite"]').text()).toBe('')

    await wrapper.get('input').setValue('Casa7lua!mar9')

    expect(wrapper.findAll('.strength__segment--on')).toHaveLength(4)
    expect(wrapper.get('[aria-live="polite"]').text()).toBe('Força da senha: forte')
    expect(wrapper.get('input').attributes('autocomplete')).toBe('new-password')
  })
})
