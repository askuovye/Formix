import type { DataEnvelope, MessageResponse, User } from '@/types/api'

import { http } from './client'

export async function fetchCurrentUser(): Promise<User> {
  const response = await http.get<DataEnvelope<User>>('/auth/me')

  return response.data.data
}

export async function logout(): Promise<void> {
  await http.post('/auth/logout')
}

export async function resendEmailVerification(): Promise<string> {
  const response = await http.post<MessageResponse>('/auth/email/verification-notification')

  return response.data.message
}
