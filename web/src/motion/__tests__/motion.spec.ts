import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { describe, expect, it } from 'vitest'

import { distance, duration, ease } from '../tokens'

// A raiz do Vitest é web/ (vitest.config.ts).
const motionScss = readFileSync(resolve('src/styles/tokens/_motion.scss'), 'utf8')

// Só o bloco principal: os de prefers-reduced-motion e da troca de tema zeram as durações de propósito.
const mainBlock = motionScss.split('@include reduced-motion')[0] ?? ''

function cssToken(name: string): string {
  const match = new RegExp(`--${name}:\\s*([^;]+);`).exec(mainBlock)

  if (!match?.[1]) {
    throw new Error(`Token --${name} não encontrado em _motion.scss`)
  }

  return match[1].trim()
}

describe('tokens de movimento: TS espelha o CSS', () => {
  it.each(Object.entries(duration))('--duration-%s', (name, seconds) => {
    expect(cssToken(`duration-${name}`)).toBe(`${Math.round(seconds * 1000)}ms`)
  })

  it.each([
    ['out', ease.out],
    ['in-out', ease.inOut],
  ] as const)('--ease-%s', (name, curve) => {
    expect(cssToken(`ease-${name}`)).toBe(`cubic-bezier(${curve.join(', ')})`)
  })

  it.each(Object.entries(distance))('--distance-%s', (name, px) => {
    expect(cssToken(`distance-${name}`)).toBe(`${px}px`)
  })

  it('cobre todos os tokens do CSS', () => {
    const cssNames = [...mainBlock.matchAll(/--(duration|ease|distance)-([\w-]+):/g)].map(
      (m) => m[0],
    )

    expect(cssNames).toHaveLength(
      Object.keys(duration).length + Object.keys(ease).length + Object.keys(distance).length,
    )
  })
})
