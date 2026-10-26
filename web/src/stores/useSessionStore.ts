import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { fetchCurrentUser, logout as apiLogout } from '@/api/auth'
import { ApiError, setUnauthorizedHandler } from '@/api/client'
import { queryClient } from '@/app/providers/queryClient'
import type { User } from '@/types/api'

type SessionStatus = 'unknown' | 'authenticated' | 'guest'

/** Usuário logado (estado de cliente: AGENTS.md §7). */
export const useSessionStore = defineStore('session', () => {
  const user = ref<User | null>(null)
  const status = ref<SessionStatus>('unknown')
  let pending: Promise<void> | null = null

  const isAuthenticated = computed(() => status.value === 'authenticated')

  function setUser(next: User | null): void {
    // Trocar de usuário (ou sair) descarta os dados de servidor do anterior.
    if (user.value && user.value.id !== next?.id) {
      queryClient.clear()
    }

    user.value = next
    status.value = next ? 'authenticated' : 'guest'
  }

  function fetchUser(): Promise<void> {
    pending ??= fetchCurrentUser()
      .then(setUser)
      .catch((error: unknown) => {
        if (error instanceof ApiError && error.status === 401) {
          setUser(null)
          return
        }
        throw error
      })
      .finally(() => {
        pending = null
      })

    return pending
  }

  /** Descobre se há sessão (GET /auth/me) na primeira vez. Chamadas simultâneas compartilham a requisição. */
  function load(): Promise<void> {
    return status.value === 'unknown' ? fetchUser() : Promise.resolve()
  }

  /** Relê o usuário (ex.: depois de confirmar o email). */
  function refresh(): Promise<void> {
    return fetchUser()
  }

  async function logout(): Promise<void> {
    try {
      await apiLogout()
    } finally {
      setUser(null)
    }
  }

  // Qualquer 401 da API encerra a sessão local.
  setUnauthorizedHandler(() => setUser(null))

  return { user, status, isAuthenticated, setUser, load, refresh, logout }
})
