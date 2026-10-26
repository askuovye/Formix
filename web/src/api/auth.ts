import type { DataEnvelope, MessageResponse, User } from '@/types/api'

import { http } from './client'

export interface LoginPayload {
  email: string
  password: string
  remember: boolean
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  terms_accepted: true
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

/** Parâmetros do link de confirmação enviado por email (rota /verificar-email da SPA). */
export interface VerifyEmailParams {
  id: string
  hash: string
  expires: string
  signature: string
}

export async function fetchCurrentUser(): Promise<User> {
  const response = await http.get<DataEnvelope<User>>('/auth/me')

  return response.data.data
}

export async function login(payload: LoginPayload): Promise<User> {
  const response = await http.post<DataEnvelope<User>>('/auth/login', payload)

  return response.data.data
}

export async function logout(): Promise<void> {
  await http.post('/auth/logout')
}

/** A resposta é a mesma se o email já existir (D18): o usuário não fica logado. */
export async function register(payload: RegisterPayload): Promise<string> {
  const response = await http.post<MessageResponse>('/auth/register', payload)

  return response.data.message
}

export async function forgotPassword(email: string): Promise<string> {
  const response = await http.post<MessageResponse>('/auth/forgot-password', { email })

  return response.data.message
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<string> {
  const response = await http.post<MessageResponse>('/auth/reset-password', payload)

  return response.data.message
}

export async function verifyEmail({
  id,
  hash,
  expires,
  signature,
}: VerifyEmailParams): Promise<string> {
  const response = await http.get<MessageResponse>(
    `/auth/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}`,
    { params: { expires, signature } },
  )

  return response.data.message
}

export async function resendEmailVerification(): Promise<string> {
  const response = await http.post<MessageResponse>('/auth/email/verification-notification')

  return response.data.message
}
