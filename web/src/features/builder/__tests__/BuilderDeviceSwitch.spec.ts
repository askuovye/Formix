import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import BuilderDeviceSwitch from '../BuilderDeviceSwitch.vue'
import { CANVAS_DEVICE_STORAGE_KEY, useCanvasDevice } from '../canvasDevice'

describe('BuilderDeviceSwitch', () => {
  afterEach(() => {
    useCanvasDevice().setDevice('desktop')
    localStorage.clear()
  })

  it('alterna a largura da prévia, marca o escolhido e lembra a escolha', async () => {
    const wrapper = mount(BuilderDeviceSwitch)
    const [desktop, mobile] = wrapper.findAll('button')

    expect(wrapper.get('[role="group"]').attributes('aria-label')).toBe('Largura da prévia')
    expect(desktop?.attributes('aria-pressed')).toBe('true')
    expect(mobile?.attributes('aria-label')).toBe('Ver como no celular')

    await mobile?.trigger('click')

    expect(useCanvasDevice().device.value).toBe('mobile')
    expect(mobile?.attributes('aria-pressed')).toBe('true')
    expect(desktop?.attributes('aria-pressed')).toBe('false')
    expect(localStorage.getItem(CANVAS_DEVICE_STORAGE_KEY)).toBe('mobile')
  })
})
