import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastTone = 'neutral' | 'success' | 'danger'

export interface Toast {
  id: number
  title: string
  description?: string
  tone: ToastTone
  /** Link de ação (ex.: baixar o arquivo pronto). */
  action?: { label: string; href: string }
}

/** Fila de avisos exibidos pelo AppToaster (montado uma vez no App.vue). */
export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])
  let nextId = 1

  function show(toast: Omit<Toast, 'id' | 'tone'> & { tone?: ToastTone }): number {
    const id = nextId++
    toasts.value.push({ tone: 'neutral', ...toast, id })

    return id
  }

  function dismiss(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  return { toasts, show, dismiss }
})
