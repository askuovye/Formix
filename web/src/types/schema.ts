/**
 * Schema do formulário (docs/api.md e docs/frontend-contract-requests.md).
 * Uniões discriminadas por `kind` e por `type` (AGENTS.md §7).
 */

export type InputType =
  | 'short_text'
  | 'long_text'
  | 'email'
  | 'number'
  | 'date'
  | 'single_choice'
  | 'multiple_choice'
  | 'file'
  | 'rating'
  | 'scale'

export type BlockType = 'section_title' | 'text' | 'divider' | 'page_break'

export type Strictness = 'leve' | 'medio' | 'estrito'

export type FileAccept = 'pdf' | 'image' | 'document' | 'spreadsheet' | 'text'

export type RuleType =
  | 'min_length'
  | 'max_length'
  | 'min_distinct_chars'
  | 'no_repeated_chars'
  | 'no_sequences'
  | 'no_only_symbols'
  | 'blocklist'
  | 'min_words'
  | 'cpf'
  | 'cnpj'
  | 'phone_br'
  | 'cep'

interface RuleBase {
  /** Mensagem própria do dono (opcional, até 200). */
  message?: string
}

export type Rule = RuleBase &
  (
    | { type: 'min_length' | 'max_length' | 'min_distinct_chars' | 'min_words'; value: number }
    | { type: 'no_repeated_chars'; threshold: number }
    | { type: 'no_sequences'; value?: number }
    | { type: 'blocklist'; values: string[] }
    | { type: 'no_only_symbols' | 'cpf' | 'cnpj' | 'phone_br' | 'cep' }
  )

export type VisibilityOperator =
  'eq' | 'neq' | 'lt' | 'lte' | 'gt' | 'gte' | 'includes' | 'excludes' | 'answered' | 'not_answered'

export interface Visibility {
  action: 'show' | 'hide'
  when: { field: string; operator: VisibilityOperator; value?: string | number }
}

export interface ChoiceOption {
  value: string
  label: string
}

interface InputBase {
  id: string
  kind: 'input'
  label: string
  help_text?: string | null
  required?: boolean
  visibility?: Visibility | null
}

interface TextLikeBase extends InputBase {
  placeholder?: string | null
  rules?: Rule[]
  /** Atalho do builder: o servidor troca pelas regras e não o devolve. */
  strictness?: Strictness
}

export interface ShortTextField extends TextLikeBase {
  type: 'short_text'
}

export interface LongTextField extends TextLikeBase {
  type: 'long_text'
}

export interface EmailField extends InputBase {
  type: 'email'
  placeholder?: string | null
}

export interface NumberField extends InputBase {
  type: 'number'
  placeholder?: string | null
}

export interface DateField extends InputBase {
  type: 'date'
}

export interface SingleChoiceField extends InputBase {
  type: 'single_choice'
  display?: 'radio' | 'select'
  options: ChoiceOption[]
}

export interface MultipleChoiceField extends InputBase {
  type: 'multiple_choice'
  options: ChoiceOption[]
}

export interface FileField extends InputBase {
  type: 'file'
  file: { accept: FileAccept[]; max_files: number; max_size_mb: number }
}

export interface RatingField extends InputBase {
  type: 'rating'
  rating: { max: number }
}

export interface ScaleField extends InputBase {
  type: 'scale'
  scale: { min: number; max: number; min_label: string | null; max_label: string | null }
}

export type InputField =
  | ShortTextField
  | LongTextField
  | EmailField
  | NumberField
  | DateField
  | SingleChoiceField
  | MultipleChoiceField
  | FileField
  | RatingField
  | ScaleField

export interface TextBlock {
  id: string
  kind: 'block'
  type: 'section_title' | 'text'
  text: string
}

export interface PlainBlock {
  id: string
  kind: 'block'
  type: 'divider' | 'page_break'
}

export type BlockItem = TextBlock | PlainBlock

export type SchemaItem = InputField | BlockItem

export type ThemePreset = 'classic' | 'minimal' | 'dark' | 'modern' | 'custom'

/** Tema do formulário (docs/frontend-contract-requests.md §4). Cores em "#RRGGBB". */
export interface FormTheme {
  preset: ThemePreset
  colors: { primary: string; background: string; surface: string; text: string }
  radius: 'none' | 'small' | 'medium' | 'large'
  font: 'inter' | 'system' | 'serif'
  button_style: 'solid' | 'outline'
  alignment: 'left' | 'center'
  /** `url` vem nas respostas da API (pública); não precisa ser enviada. */
  cover: {
    asset_id: string
    fit: 'cover' | 'contain'
    overlay_opacity: number
    url?: string
  } | null
  logo: { asset_id: string; url?: string } | null
}

export interface FormSchema {
  fields: SchemaItem[]
  /** Ausente = Clássico (o servidor completa ao salvar). */
  theme?: FormTheme
}

/** Limites do contrato usados pelo builder. */
export const SCHEMA_LIMITS = {
  idPattern: /^[a-z][a-z0-9_]{0,39}$/,
  labelMax: 200,
  helpTextMax: 500,
  placeholderMax: 120,
  sectionTitleMax: 200,
  textBlockMax: 2000,
} as const
