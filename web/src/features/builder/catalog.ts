import type { IconName } from '@/components/icons/iconNames'
import { builder } from '@/strings'
import type {
  DateField,
  EmailField,
  FileField,
  InputField,
  LongTextField,
  MultipleChoiceField,
  NumberField,
  RatingField,
  ScaleField,
  SchemaItem,
  ShortTextField,
  SingleChoiceField,
} from '@/types/schema'

export type PaletteGroup = 'basic' | 'advanced' | 'element'

export type PaletteKey = keyof typeof builder.types

export interface PaletteEntry {
  key: PaletteKey
  group: PaletteGroup
  icon: IconName
  /** Prefixo do id gerado (`texto_1`, `cpf_2`…): o id não muda depois, nem se o rótulo mudar. */
  idPrefix: string
  create: (id: string) => SchemaItem
}

const d = builder.defaults

function input<T extends InputField>(
  field: Omit<T, 'kind' | 'label' | 'required'> & { label?: string },
): T {
  return { kind: 'input', label: d.label, required: false, ...field } as T
}

function options() {
  return [1, 2].map((n) => ({ value: `opcao_${n}`, label: d.option(n) }))
}

/** Paleta do builder, na ordem exibida (D32: Avançados = CPF, CNPJ e CEP). */
export const PALETTE: readonly PaletteEntry[] = [
  {
    key: 'short_text',
    group: 'basic',
    icon: 'type',
    idPrefix: 'texto',
    create: (id) => input<ShortTextField>({ id, type: 'short_text' }),
  },
  {
    key: 'long_text',
    group: 'basic',
    icon: 'align-left',
    idPrefix: 'paragrafo',
    create: (id) => input<LongTextField>({ id, type: 'long_text' }),
  },
  {
    key: 'email',
    group: 'basic',
    icon: 'mail',
    idPrefix: 'email',
    create: (id) => input<EmailField>({ id, type: 'email', label: builder.types.email }),
  },
  {
    key: 'phone',
    group: 'basic',
    icon: 'phone',
    idPrefix: 'telefone',
    create: (id) =>
      input<ShortTextField>({
        id,
        type: 'short_text',
        label: builder.types.phone,
        rules: [{ type: 'phone_br' }],
      }),
  },
  {
    key: 'number',
    group: 'basic',
    icon: 'hash',
    idPrefix: 'numero',
    create: (id) => input<NumberField>({ id, type: 'number' }),
  },
  {
    key: 'date',
    group: 'basic',
    icon: 'calendar',
    idPrefix: 'data',
    create: (id) => input<DateField>({ id, type: 'date' }),
  },
  {
    key: 'single_choice',
    group: 'basic',
    icon: 'circle-dot',
    idPrefix: 'escolha',
    create: (id) =>
      input<SingleChoiceField>({ id, type: 'single_choice', display: 'radio', options: options() }),
  },
  {
    key: 'multiple_choice',
    group: 'basic',
    icon: 'square-check',
    idPrefix: 'multipla',
    create: (id) => input<MultipleChoiceField>({ id, type: 'multiple_choice', options: options() }),
  },
  {
    key: 'dropdown',
    group: 'basic',
    icon: 'list',
    idPrefix: 'lista',
    create: (id) =>
      input<SingleChoiceField>({
        id,
        type: 'single_choice',
        display: 'select',
        options: options(),
      }),
  },
  {
    key: 'rating',
    group: 'basic',
    icon: 'star',
    idPrefix: 'estrelas',
    create: (id) => input<RatingField>({ id, type: 'rating', rating: { max: 5 } }),
  },
  {
    key: 'scale',
    group: 'basic',
    icon: 'gauge',
    idPrefix: 'escala',
    create: (id) =>
      input<ScaleField>({
        id,
        type: 'scale',
        scale: { min: 0, max: 10, min_label: null, max_label: null },
      }),
  },
  {
    key: 'file',
    group: 'basic',
    icon: 'upload',
    idPrefix: 'arquivo',
    create: (id) =>
      input<FileField>({
        id,
        type: 'file',
        file: { accept: ['pdf', 'image'], max_files: 1, max_size_mb: 5 },
      }),
  },
  {
    key: 'cpf',
    group: 'advanced',
    icon: 'id-card',
    idPrefix: 'cpf',
    create: (id) =>
      input<ShortTextField>({
        id,
        type: 'short_text',
        label: builder.types.cpf,
        rules: [{ type: 'cpf' }],
      }),
  },
  {
    key: 'cnpj',
    group: 'advanced',
    icon: 'building-2',
    idPrefix: 'cnpj',
    create: (id) =>
      input<ShortTextField>({
        id,
        type: 'short_text',
        label: builder.types.cnpj,
        rules: [{ type: 'cnpj' }],
      }),
  },
  {
    key: 'cep',
    group: 'advanced',
    icon: 'map-pin',
    idPrefix: 'cep',
    create: (id) =>
      input<ShortTextField>({
        id,
        type: 'short_text',
        label: builder.types.cep,
        rules: [{ type: 'cep' }],
      }),
  },
  {
    key: 'section_title',
    group: 'element',
    icon: 'heading',
    idPrefix: 'titulo',
    create: (id) => ({ id, kind: 'block', type: 'section_title', text: d.sectionTitle }),
  },
  {
    key: 'text',
    group: 'element',
    icon: 'text',
    idPrefix: 'texto_apoio',
    create: (id) => ({ id, kind: 'block', type: 'text', text: d.text }),
  },
  {
    key: 'divider',
    group: 'element',
    icon: 'minus',
    idPrefix: 'divisor',
    create: (id) => ({ id, kind: 'block', type: 'divider' }),
  },
  {
    key: 'page_break',
    group: 'element',
    icon: 'separator-horizontal',
    idPrefix: 'etapa',
    create: (id) => ({ id, kind: 'block', type: 'page_break' }),
  },
]

const RULE_KEYS: Partial<Record<string, PaletteKey>> = {
  phone_br: 'phone',
  cpf: 'cpf',
  cnpj: 'cnpj',
  cep: 'cep',
}

/** Qual entrada da paleta descreve um item (para ícone e nome do tipo no canvas). */
export function paletteEntryFor(item: SchemaItem): PaletteEntry {
  let key: PaletteKey = item.type

  if (item.type === 'short_text') {
    const special = item.rules?.map((rule) => RULE_KEYS[rule.type]).find(Boolean)
    key = special ?? 'short_text'
  } else if (item.type === 'single_choice' && item.display === 'select') {
    key = 'dropdown'
  }

  return PALETTE.find((entry) => entry.key === key) ?? (PALETTE[0] as PaletteEntry)
}

/** Primeiro id livre com o prefixo: `texto_1`, `texto_2`… (padrão do contrato: ^[a-z][a-z0-9_]{0,39}$). */
export function nextId(prefix: string, taken: ReadonlySet<string>): string {
  for (let n = 1; ; n++) {
    const id = `${prefix}_${n}`
    if (!taken.has(id)) return id
  }
}
