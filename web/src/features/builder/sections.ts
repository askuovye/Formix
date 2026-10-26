import type { IconName } from '@/components/icons/iconNames'

/** Seções da barra lateral do builder (rail). */
export type BuilderSection = 'fields' | 'elements' | 'logic' | 'design' | 'settings'

export interface BuilderSectionEntry {
  key: BuilderSection
  icon: IconName
  /** Ainda não disponível: aparece desabilitada com "Em breve". */
  soon?: boolean
}

export const BUILDER_SECTIONS: readonly BuilderSectionEntry[] = [
  { key: 'fields', icon: 'text-cursor-input' },
  { key: 'elements', icon: 'shapes' },
  { key: 'logic', icon: 'git-branch' },
  { key: 'design', icon: 'palette' },
  { key: 'settings', icon: 'settings' },
]
