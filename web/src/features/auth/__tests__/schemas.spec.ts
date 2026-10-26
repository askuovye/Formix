import * as v from 'valibot'
import { describe, expect, it } from 'vitest'

import { safeRedirect } from '../redirect'
import { forgotPasswordSchema, loginSchema, registerSchema, resetPasswordSchema } from '../schemas'

function fieldErrors(schema: v.GenericSchema, input: unknown): Record<string, string | undefined> {
  const result = v.safeParse(schema, input)

  if (result.success) {
    return {}
  }

  const nested: Record<string, string[] | undefined> = v.flatten(result.issues).nested ?? {}

  return Object.fromEntries(Object.entries(nested).map(([key, messages]) => [key, messages?.[0]]))
}

const validRegister = {
  name: 'Pessoa Teste',
  email: 'pessoa@example.com',
  password: 'senhaForte123',
  password_confirmation: 'senhaForte123',
  terms_accepted: true,
}

describe('schemas de autenticação (espelho do back-end)', () => {
  it('normaliza o email como o back-end: sem espaços e em minúsculas', () => {
    const output = v.parse(forgotPasswordSchema, { email: '  Pessoa@Example.COM ' })

    expect(output.email).toBe('pessoa@example.com')
  })

  it.each([
    ['', 'Informe seu e-mail.'],
    ['sem-arroba', 'Informe um e-mail válido.'],
    [`${'a'.repeat(250)}@x.com`, 'Use no máximo 255 caracteres.'],
  ])('email inválido: %j', (email, message) => {
    expect(fieldErrors(forgotPasswordSchema, { email }).email).toBe(message)
  })

  it('login só exige a senha preenchida (sem regras de força)', () => {
    expect(fieldErrors(loginSchema, { email: 'a@b.co', password: '1', remember: false })).toEqual(
      {},
    )
    expect(
      fieldErrors(loginSchema, { email: 'a@b.co', password: '', remember: false }).password,
    ).toBe('Informe sua senha.')
  })

  it('cadastro válido passa e o nome sai sem espaços nas pontas', () => {
    const output = v.parse(registerSchema, { ...validRegister, name: '  Pessoa Teste  ' })

    expect(output.name).toBe('Pessoa Teste')
  })

  it.each([
    ['curta1', 'A senha precisa ter pelo menos 8 caracteres.'],
    [`a1${'b'.repeat(71)}`, 'A senha pode ter no máximo 72 caracteres.'],
    ['12345678', 'A senha precisa ter pelo menos uma letra.'],
    ['somenteletras', 'A senha precisa ter pelo menos um número.'],
  ])('senha nova recusada: %s', (password, message) => {
    expect(
      fieldErrors(registerSchema, { ...validRegister, password, password_confirmation: password })
        .password,
    ).toBe(message)
  })

  it('aceita letras e números de qualquer alfabeto, como o Laravel (\\pL, \\pN)', () => {
    const password = 'ação2026senha'

    expect(fieldErrors(resetPasswordSchema, { password, password_confirmation: password })).toEqual(
      {},
    )
  })

  it('confirmação diferente: erro no campo de confirmação', () => {
    expect(
      fieldErrors(resetPasswordSchema, {
        password: 'senhaForte123',
        password_confirmation: 'outraSenha123',
      }),
    ).toEqual({ password_confirmation: 'As senhas não conferem.' })
  })

  it('exige aceitar os termos', () => {
    expect(fieldErrors(registerSchema, { ...validRegister, terms_accepted: false })).toEqual({
      terms_accepted: 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.',
    })
  })

  it('nome obrigatório e com até 120 caracteres', () => {
    expect(fieldErrors(registerSchema, { ...validRegister, name: '   ' }).name).toBe(
      'Informe seu nome.',
    )
    expect(fieldErrors(registerSchema, { ...validRegister, name: 'a'.repeat(121) }).name).toBe(
      'Use no máximo 120 caracteres.',
    )
  })
})

describe('safeRedirect', () => {
  it.each([
    ['/painel/configuracoes', '/painel/configuracoes'],
    ['/painel?aba=1', '/painel?aba=1'],
    ['//outro.site', '/painel'],
    ['/\\outro.site', '/painel'],
    ['https://outro.site', '/painel'],
    [undefined, '/painel'],
    [['/a', '/b'], '/painel'],
  ])('%j → %s', (input, expected) => {
    expect(safeRedirect(input)).toBe(expected)
  })
})
