import { defineStore } from 'pinia'
import { computed, ref, shallowRef, toRaw } from 'vue'

import type { FormSchema, SchemaItem } from '@/types/schema'

import { PALETTE, type PaletteKey, nextId, paletteEntryFor } from './catalog'
import { isTextField } from './rules'
import { type ItemErrors, validateItems } from './schemaErrors'

/** Quantos passos o desfazer guarda. */
const HISTORY_LIMIT = 100
/** Edições seguidas no mesmo campo e propriedade dentro deste intervalo viram um passo só. */
const COALESCE_MS = 1000

/**
 * Cópia profunda que desembrulha proxies em todos os níveis: métodos como
 * `filter` num array reativo devolvem proxies dos elementos, que o
 * `structuredClone` recusa (DataCloneError).
 */
function clone<T>(value: T): T {
  const raw = toRaw(value)

  if (Array.isArray(raw)) {
    return raw.map((entry: unknown) => clone(entry)) as T
  }

  if (raw !== null && typeof raw === 'object') {
    return Object.fromEntries(Object.entries(raw).map(([key, entry]) => [key, clone(entry)])) as T
  }

  return raw
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

  /** Erros do último 422 do servidor, por id do item (somem quando o item muda). */
  const serverErrors = shallowRef<Map<string, ItemErrors>>(new Map())
  /** Validação local: espelho do back-end, recalculada a cada mudança. */
  const clientErrors = computed(() => validateItems(items.value))
  const hasClientErrors = computed(() => clientErrors.value.size > 0)

  function errorsFor(id: string): ItemErrors {
    return { ...serverErrors.value.get(id), ...clientErrors.value.get(id) }
  }

  function setServerErrors(errors: Map<string, ItemErrors>): void {
    serverErrors.value = errors
  }

  function clearServerErrors(id: string): void {
    if (!serverErrors.value.has(id)) return
    const next = new Map(serverErrors.value)
    next.delete(id)
    serverErrors.value = next
  }

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
    serverErrors.value = new Map()
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
    clearServerErrors(id)
  }

  /**
   * Depois de salvar: o servidor trocou o nível de rigor pelas regras do nível.
   * Adota essas regras nos itens que não mudaram desde o envio. Não entra no
   * histórico nem dispara novo salvamento (o servidor já tem esse estado).
   */
  function adoptServerRules(sent: readonly SchemaItem[], saved: readonly SchemaItem[]): void {
    for (const sentItem of sent) {
      if (sentItem.kind !== 'input' || !isTextField(sentItem) || !sentItem.strictness) continue

      const local = items.value.find((item) => item.id === sentItem.id)
      const fromServer = saved.find((item) => item.id === sentItem.id)

      if (
        local?.kind === 'input' &&
        isTextField(local) &&
        fromServer?.kind === 'input' &&
        isTextField(fromServer) &&
        local.strictness === sentItem.strictness &&
        JSON.stringify(local.rules ?? []) === JSON.stringify(sentItem.rules ?? [])
      ) {
        local.rules = clone(fromServer.rules ?? [])
        delete local.strictness
      }
    }
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

  /** Cópia dos itens como estão agora (o que vai para o servidor ao salvar). */
  function snapshot(): SchemaItem[] {
    return clone(items.value)
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
    serverErrors,
    clientErrors,
    hasClientErrors,
    errorsFor,
    setServerErrors,
    adoptServerRules,
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
    snapshot,
    announce,
  }
})
