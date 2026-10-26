// Gera src/components/icons/lucide-subset.json só com os ícones de ICON_NAMES,
// para não embarcar a coleção Lucide inteira. Uso: `npm run icons`.
import { readFile, writeFile } from 'node:fs/promises'
import { createRequire } from 'node:module'

import type { IconifyJSON } from '@iconify/types'
import { getIcons } from '@iconify/utils'

import { ICON_NAMES } from '../src/components/icons/iconNames.ts'

const require = createRequire(import.meta.url)
const collection = JSON.parse(
  await readFile(require.resolve('@iconify-json/lucide/icons.json'), 'utf8'),
) as IconifyJSON

const subset = getIcons(collection, [...ICON_NAMES], true)
const missing = ICON_NAMES.filter((name) => !subset?.icons[name] && !subset?.aliases?.[name])

if (!subset || missing.length > 0) {
  throw new Error(`Ícones inexistentes no Lucide: ${missing.join(', ')}`)
}

const output = new URL('../src/components/icons/lucide-subset.json', import.meta.url)
await writeFile(output, `${JSON.stringify(subset, null, 2)}\n`)

console.log(`lucide-subset.json: ${ICON_NAMES.length} ícones`)
