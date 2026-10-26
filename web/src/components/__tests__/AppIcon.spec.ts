import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import AppIcon from '../AppIcon.vue'
import { ICON_NAMES } from '../icons/iconNames'
import lucideSubset from '../icons/lucide-subset.json'

describe('AppIcon', () => {
  it('o subconjunto gerado tem exatamente os ícones da lista (rode `npm run icons`)', () => {
    const aliases: Record<string, { parent: string }> = lucideSubset.aliases ?? {}
    // Um alias (ex.: trash-2) traz junto o ícone-pai (trash), que não está na lista.
    const parents = new Set(Object.values(aliases).map((alias) => alias.parent))
    const requested = [...Object.keys(lucideSubset.icons), ...Object.keys(aliases)].filter(
      (name) => (ICON_NAMES as readonly string[]).includes(name) || !parents.has(name),
    )

    expect(requested.sort()).toEqual([...ICON_NAMES].sort())
  })

  it('renderiza o SVG sem buscar nada na rede', () => {
    const wrapper = mount(AppIcon, { props: { name: 'check' } })

    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('sem label, é decorativo', () => {
    const svg = mount(AppIcon, { props: { name: 'check' } }).find('svg')

    expect(svg.attributes('aria-hidden')).toBe('true')
    expect(svg.attributes('role')).toBeUndefined()
  })

  it('com label, é anunciado como imagem', () => {
    const svg = mount(AppIcon, { props: { name: 'trash-2', label: 'Excluir' } }).find('svg')

    expect(svg.attributes('role')).toBe('img')
    expect(svg.attributes('aria-label')).toBe('Excluir')
    expect(svg.attributes('aria-hidden')).toBeUndefined()
  })
})
