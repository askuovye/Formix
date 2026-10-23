import { describe, expect, it } from 'vitest'

import fixtures from '@fixtures/validation-cases.json'

describe('fixtures de validação compartilhadas', () => {
  it('carrega o arquivo compartilhado com o backend', () => {
    expect(fixtures).toHaveProperty('rules')
  })
})
