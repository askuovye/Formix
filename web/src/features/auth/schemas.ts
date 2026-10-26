import * as v from 'valibot'

import { auth } from '@/strings'

/**
 * Espelho das regras do back-end (Form Requests de api/app/Http/Requests/Auth e
 * Password::defaults() em AppServiceProvider). O servidor revalida tudo.
 */

const t = auth.validation

export const NAME_MAX_LENGTH = 120
export const EMAIL_MAX_LENGTH = 255
export const PASSWORD_MIN_LENGTH = 8
export const PASSWORD_MAX_LENGTH = 72

/** Igual ao NormalizesEmail do back-end: sem espaços nas pontas e em minúsculas. */
const email = v.pipe(
  v.string(),
  v.trim(),
  v.toLowerCase(),
  v.nonEmpty(t.emailRequired),
  v.email(t.emailInvalid),
  v.maxLength(EMAIL_MAX_LENGTH, t.emailMax),
)

/** Senha nova: 8 a 72 caracteres, com pelo menos uma letra e um número (Unicode, como no Laravel). */
const newPassword = v.pipe(
  v.string(),
  v.nonEmpty(t.passwordRequired),
  v.minLength(PASSWORD_MIN_LENGTH, t.passwordMin),
  v.maxLength(PASSWORD_MAX_LENGTH, t.passwordMax),
  v.regex(/\p{L}/u, t.passwordLetters),
  v.regex(/\p{N}/u, t.passwordNumbers),
)

export const loginSchema = v.object({
  email,
  password: v.pipe(v.string(), v.nonEmpty(t.passwordRequired)),
  remember: v.boolean(),
})

export const registerSchema = v.pipe(
  v.object({
    name: v.pipe(
      v.string(),
      v.trim(),
      v.nonEmpty(t.nameRequired),
      v.maxLength(NAME_MAX_LENGTH, t.nameMax),
    ),
    email,
    password: newPassword,
    password_confirmation: v.string(),
    terms_accepted: v.literal(true, t.termsRequired),
  }),
  v.forward(
    v.partialCheck(
      [['password'], ['password_confirmation']],
      (input) => input.password === input.password_confirmation,
      t.passwordMismatch,
    ),
    ['password_confirmation'],
  ),
)

export const forgotPasswordSchema = v.object({ email })

export const resetPasswordSchema = v.pipe(
  v.object({
    password: newPassword,
    password_confirmation: v.string(),
  }),
  v.forward(
    v.partialCheck(
      [['password'], ['password_confirmation']],
      (input) => input.password === input.password_confirmation,
      t.passwordMismatch,
    ),
    ['password_confirmation'],
  ),
)
