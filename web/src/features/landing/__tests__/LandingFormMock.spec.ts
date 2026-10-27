import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import LandingFormMock from '../LandingFormMock.vue'

describe('LandingFormMock', () => {
  it('escolher uma opção e enviar mostra o agradecimento dela e soma um lead', async () => {
    const wrapper = mount(LandingFormMock, { attachTo: document.body })

    expect(wrapper.get('.form-mock__leads').text()).toContain('+12')
    const radios = wrapper.findAll('input[type="radio"]')
    expect(radios).toHaveLength(4)
    expect((radios[0]?.element as HTMLInputElement).checked).toBe(true)

    await radios[1]?.setValue(true)
    expect(wrapper.get('.form-mock__option--checked').text()).toBe('Coletar feedback')

    await wrapper.get('button[aria-label="Enviar resposta"]').trigger('submit')
    await flushPromises()

    const status = wrapper.get('[role="status"]')
    expect(status.text()).toContain('Obrigado!')
    expect(status.text()).toContain('sem nenhum “asdf”')
    expect(wrapper.get('.form-mock__leads').text()).toContain('+13')
    expect(wrapper.get('.form-mock__leads').text()).toContain('contando com você')
    // O foco vai para o botão novo (o de enviar sumiu).
    expect(document.activeElement?.textContent?.trim()).toBe('Responder de novo')
    wrapper.unmount()
  })

  it('"Responder de novo" volta ao formulário com a escolha e cada envio soma mais um', async () => {
    const wrapper = mount(LandingFormMock, { attachTo: document.body })

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Responder de novo')
      ?.trigger('click')
    await flushPromises()

    expect(wrapper.find('form').exists()).toBe(true)
    expect(document.activeElement).toBe(wrapper.get('input[type="radio"]').element)

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('.form-mock__leads').text()).toContain('+14')
    wrapper.unmount()
  })
})
