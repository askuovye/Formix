import { defineStore } from 'pinia'
import { computed, ref, shallowRef, toRaw } from 'vue'

import type { FormSchema, SchemaItem } from '@/types/schema'

import { PALETTE, type PaletteKey, nextId, paletteEntryFor } from './catalog'

/** Quantos passos o desfazer guarda. */
const HISTORY_LIMIT = 100
/** Edições seguidas no mesmo campo e propriedade dentro deste intervalo viram um passo só. */
const COALESCE_MS = 1000

function clone<T>(value: T): T {
  return structuredClone(toRaw(value))
}

/**
 * Estado do builder (estado de cliente, AGENTS.md §7): itens do rascunho,
 * seleção e histórico de desfazer/refazer. Salvar fica com o useAutosave.
 */
export const useBuilderStore = defineStore('builder', () => {
  const formId = ref<string | null>(null)
  const items = ref<SchemaItem[]>([])
  /** Tema (fase 4): preservado como veio, para não ser apagado ao salvar. */
  const theme = shallowRef<unknown>(undefined)
  const selectedId = ref<string | null>(null)
  /** Sobe a cada mudança no conteúdo: é o que o autosave observa. */
  const revision = ref(0)
  /** Último aviso para leitores de tela (mover, remover). */
  const announcement = ref('')

  const past = shallowRef<SchemaItem[][]>([])
  const future = shallowRef<SchemaItem[][]>([])
  let lastCommit: { key: string; at: number } | null = null

  const canUndo = computed(() => past.value.length > 0)
  const canRedo = computed(() => future.value.length > 0)
  const selected = computed(() => items.value.find((item) => item.id === selectedId.value) ?? null)

  const schema = computed<FormSchema>(() => ({
    fields: items.value,
    ...(theme.value === undefined ? {} : { theme: theme.value }),
  }))

  function load(id: string, source: FormSchema): void {
    formId.value = id
    // Schemas antigos podem vir sem `kind`: são campos (contrato §1).
    items.value = clone(source.fields).map(
      (item) => ({ ...item, kind: item.kind ?? 'input' }) as SchemaItem,
    )
    theme.value = source.theme
    selectedId.value = null
    past.value = []
    future.value = []
    lastCommit = null
    revision.value = 0
  }

  /** Aplica uma mudança guardando o estado anterior no histórico. */
  function commit(change: () => void, coalesceKey?: string): void {
    const now = Date.now()
    const coalesce =
      coalesceKey !== undefined &&
      lastCommit?.key === coalesceKey &&
      now - lastCommit.at < COALESCE_MS

    if (!coalesce) {
      past.value = [...past.value, clone(items.value)].slice(-HISTORY_LIMIT)
    }

    future.value = []
    lastCommit = coalesceKey === undefined ? null : { key: coalesceKey, at: now }
    change()
    revision.value++
  }

  function takenIds(): Set<string> {
    return new Set(items.value.map((item) => item.id))
  }

  /** Cria um item novo da paleta, com id livre, sem inserir (usado também pelo arrastar). */
  function createItem(key: PaletteKey): SchemaItem {
    const entry = PALETTE.find((candidate) => candidate.key === key)

    if (!entry) {
      throw new Error(`Tipo desconhecido na paleta: ${key}`)
    }

    return entry.create(nextId(entry.idPrefix, takenIds()))
  }

  function indexOf(id: string): number {
    return items.value.findIndex((item) => item.id === id)
  }

  /** Insere na posição pedida; sem posição, logo após o selecionado (ou no fim). */
  function insert(item: SchemaItem, index?: number): void {
    // Item vindo do arrastar foi criado antes: garante que o id ainda está livre.
    const fresh = takenIds().has(item.id)
      ? { ...item, id: nextId(paletteEntryFor(item).idPrefix, takenIds()) }
      : item
    const selectedIndex = selectedId.value ? indexOf(selectedId.value) : -1
    const at = index ?? (selectedIndex >= 0 ? selectedIndex + 1 : items.value.length)

    commit(() => items.value.splice(at, 0, fresh))
    selectedId.value = fresh.id
  }

  function add(key: PaletteKey, index?: number): void {
    insert(createItem(key), index)
  }

  function move(from: number, to: number): void {
    const item = items.value[from]

    if (!item || from === to || to < 0 || to >= items.value.length) return

    commit(() => {
      items.value.splice(from, 1)
      items.value.splice(to, 0, item)
    })
  }

  function duplicate(id: string): void {
    const index = indexOf(id)
    const source = items.value[index]

    if (!source) return

    const copy = { ...clone(source), id: nextId(paletteEntryFor(source).idPrefix, takenIds()) }
    commit(() => items.value.splice(index + 1, 0, copy))
    selectedId.value = copy.id
  }

  function remove(id: string): void {
    const index = indexOf(id)

    if (index < 0) return

    commit(() => items.value.splice(index, 1))

    if (selectedId.value === id) {
      selectedId.value = (items.value[index] ?? items.value[index - 1])?.id ?? null
    }
  }

  /**
   * Altera um item. `change` recebe o próprio item (já estreitado por quem chama).
   * `field` identifica a propriedade para juntar a digitação num passo só de desfazer.
   */
  function update(id: string, change: (item: SchemaItem) => void, field = 'item'): void {
    const item = items.value.find((candidate) => candidate.id === id)

    if (!item) return

    commit(() => change(item), `${id}:${field}`)
  }

  function restore(from: typeof past, to: typeof future): void {
    const snapshot = from.value[from.value.length - 1]

    if (!snapshot) return

    to.value = [...to.value, clone(items.value)]
    from.value = from.value.slice(0, -1)
    items.value = snapshot
    lastCommit = null
    revision.value++

    if (selectedId.value && indexOf(selectedId.value) < 0) {
      selectedId.value = null
    }
  }

  function undo(): void {
    restore(past, future)
  }

  function redo(): void {
    restore(future, past)
  }

  function select(id: string | null): void {
    selectedId.value = id
  }

  function announce(message: string): void {
    announcement.value = message
  }

  return {
    formId,
    items,
    selectedId,
    selected,
    revision,
    announcement,
    canUndo,
    canRedo,
    schema,
    load,
    createItem,
    insert,
    add,
    move,
    duplicate,
    remove,
    update,
    undo,
    redo,
    select,
    announce,
  }
})
