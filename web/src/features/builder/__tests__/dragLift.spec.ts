import { afterEach, describe, expect, it } from 'vitest'

import { liftSession, pointerOf, useDragLift } from '../dragLift'

function card(): HTMLElement {
  const item = document.createElement('li')
  item.className = 'canvas__entry canvas__ghost'
  item.id = 'item'
  item.getBoundingClientRect = () => new DOMRect(100, 200, 300, 80)
  return item
}

describe('dragLift', () => {
  afterEach(() => {
    liftSession.value = null
  })

  it('início: copia o card sem a classe da vaga e guarda onde o ponteiro pegou', () => {
    const item = card()
    const lift = useDragLift({ themed: true, ghostClass: 'canvas__ghost' })

    lift.start({ item, originalEvent: new MouseEvent('mousemove', { clientX: 130, clientY: 220 }) })

    const session = liftSession.value
    expect(session?.source).toBe(item)
    expect(session?.node).not.toBe(item)
    expect(session?.node.classList.contains('canvas__ghost')).toBe(false)
    expect(session?.node.hasAttribute('id')).toBe(false)
    expect(session).toMatchObject({ themed: true, offset: { x: 30, y: 20 }, dropped: false })
  })

  it('sem evento de ponteiro, pega pelo centro; ao soltar marca como solto', () => {
    const lift = useDragLift({ themed: false, ghostClass: 'canvas__ghost' })

    lift.start({ item: card() })
    expect(liftSession.value?.offset).toEqual({ x: 150, y: 40 })

    lift.end()
    expect(liftSession.value?.dropped).toBe(true)
  })

  it('pointerOf lê mouse e toque', () => {
    expect(pointerOf(new MouseEvent('mousemove', { clientX: 5, clientY: 6 }))).toEqual({
      x: 5,
      y: 6,
    })
    const touch = { touches: [{ clientX: 7, clientY: 8 }], changedTouches: [] } as unknown as Event
    expect(pointerOf(touch)).toEqual({ x: 7, y: 8 })
    expect(pointerOf(undefined)).toBeNull()
  })
})
