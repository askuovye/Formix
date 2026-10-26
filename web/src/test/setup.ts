// APIs de navegador ausentes no jsdom e usadas pelo Reka UI (posicionamento,
// foco) e pelo Motion (prefers-reduced-motion). Só para os testes.
import { vi } from 'vitest'

class ResizeObserverStub {
  observe(): void {}
  unobserve(): void {}
  disconnect(): void {}
}

globalThis.ResizeObserver ??= ResizeObserverStub

if (!window.matchMedia) {
  window.matchMedia = vi.fn((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
  }))
}

// O jsdom só avisa "Not implemented"; o scrollBehavior do router chama a cada navegação.
window.scrollTo = () => {}
Element.prototype.scrollIntoView ??= () => {}
Element.prototype.hasPointerCapture ??= () => false
Element.prototype.releasePointerCapture ??= () => {}
