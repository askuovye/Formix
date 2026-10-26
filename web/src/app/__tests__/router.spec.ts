import { describe, expect, it } from 'vitest'

import router from '../router'

describe('rotas', () => {
  // A 1ª navegação compila o shell inteiro sob demanda: lenta no Windows.
  it('o início leva à lista de formulários do painel', { timeout: 30_000 }, async () => {
    await router.push('/')

    expect(router.currentRoute.value.name).toBe('forms')
  })

  it('endereço desconhecido cai na página 404', async () => {
    await router.push('/nao-existe/de-jeito-nenhum')

    expect(router.currentRoute.value.name).toBe('not-found')
    expect(document.title).toBe('Página não encontrada · Formix')
  })

  it('termos e privacidade passam o documento certo para a view', () => {
    const terms = router.resolve('/termos').matched
    const privacy = router.resolve('/privacidade').matched

    expect(terms[terms.length - 1]?.props.default).toEqual({ document: 'terms' })
    expect(privacy[privacy.length - 1]?.props.default).toEqual({ document: 'privacy' })
  })

  it('define o título da aba com o nome da marca', async () => {
    await router.push('/termos')

    expect(document.title).toBe('Termos de Uso · Formix')
  })
})
