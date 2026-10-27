import { ref } from 'vue'

/** Largura da prévia no canvas do builder: a do formulário publicado ou a de um celular. */
export const CANVAS_DEVICES = ['desktop', 'mobile'] as const
export type CanvasDevice = (typeof CANVAS_DEVICES)[number]

export const CANVAS_DEVICE_STORAGE_KEY = 'formix:builder-device'

function readStored(): CanvasDevice {
  try {
    return localStorage.getItem(CANVAS_DEVICE_STORAGE_KEY) === 'mobile' ? 'mobile' : 'desktop'
  } catch {
    return 'desktop'
  }
}

// Uma escolha só para o canvas e a barrinha (estado de interface, não do formulário).
const device = ref<CanvasDevice>(readStored())

/** Escolha lembrada neste navegador; sem armazenamento, vale só nesta sessão. */
export function useCanvasDevice() {
  function setDevice(value: CanvasDevice): void {
    device.value = value
    try {
      localStorage.setItem(CANVAS_DEVICE_STORAGE_KEY, value)
    } catch {
      // Armazenamento indisponível (aba privada, bloqueio).
    }
  }

  return { device, setDevice }
}
