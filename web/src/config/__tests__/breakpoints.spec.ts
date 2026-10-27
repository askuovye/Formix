import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { describe, expect, it } from 'vitest'

import { BREAKPOINTS } from '../breakpoints'

describe('BREAKPOINTS', () => {
  it('espelha os breakpoints do SCSS', () => {
    const scss = readFileSync(resolve('src/styles/mixins/_breakpoints.scss'), 'utf8')
    const fromScss = Object.fromEntries(
      [...scss.matchAll(/(\w+): (\d+)px/g)].map(([, name, value]) => [name, Number(value)]),
    )

    expect(fromScss).toEqual(BREAKPOINTS)
  })
})
