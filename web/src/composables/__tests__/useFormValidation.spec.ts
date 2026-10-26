import * as v from 'valibot'
import { describe, expect, it } from 'vitest'
import { reactive } from 'vue'

import { ApiError } from '@/api/client'

import { useFormValidation } from '../useFormValidation'

const schema = v.object({
  email: v.pipe(v.string(), v.nonEmpty('Informe o e-mail.')),
  name: v.pipe(v.string(), v.nonEmpty('Informe o nome.')),
})

function setup(initial = { email: '', name: '' }) {
  const values = reactive({ ...initial })

  return { values, ...useFormValidation(schema, values) }
}

describe('useFormValidation', () => {
  it('erros do cliente: a primeira mensagem de cada campo', () => {
    const { validate, errors } = setup()

    expect(validate()).toBeNull()
    expect(errors.value).toEqual({ email: 'Informe o e-mail.', name: 'Informe o nome.' })
  })

  it('dados válidos: devolve a saída e limpa os erros', () => {
    const { validate, errors, values } = setup()
    validate()
    values.email = 'a@b.co'
    values.name = 'Ana'

    expect(validate()).toEqual({ email: 'a@b.co', name: 'Ana' })
    expect(errors.value).toEqual({})
  })

  it('422 do servidor vai para os campos', () => {
    const { applyServerError, errors, formError } = setup()

    applyServerError(new ApiError('Dados inválidos.', 422, { email: ['Já usado.', 'Outro.'] }))

    expect(errors.value).toEqual({ email: 'Já usado.' })
    expect(formError.value).toBeUndefined()
  })

  it('429 vira erro geral com o tempo de espera', () => {
    const { applyServerError, formError } = setup()

    applyServerError(new ApiError('Muitas tentativas.', 429, {}, 30))

    expect(formError.value).toBe('Muitas tentativas. Tente de novo em 30 segundos.')
  })

  it('outros erros viram erro geral com a mensagem da API', () => {
    const { applyServerError, formError, errors } = setup()

    applyServerError(new ApiError('Não foi possível falar com o servidor.', 0))

    expect(formError.value).toBe('Não foi possível falar com o servidor.')
    expect(errors.value).toEqual({})
  })

  it('foca o primeiro campo inválido do formulário', async () => {
    const { focusFirstError } = setup()
    const form = document.createElement('form')
    form.innerHTML =
      '<input id="a"><input id="b" aria-invalid="true"><input id="c" aria-invalid="true">'
    document.body.append(form)

    await focusFirstError(form)

    expect(document.activeElement?.id).toBe('b')
    form.remove()
  })
})
