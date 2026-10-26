import type {
  InputField,
  LongTextField,
  Rule,
  RuleType,
  ShortTextField,
  Strictness,
} from '@/types/schema'

export type TextField = ShortTextField | LongTextField

/** Limites dos parâmetros (espelho de RuleType::parameterRules no back-end). */
export const RULE_LIMITS = {
  length: { min: 1, max: 10_000 },
  minWords: { min: 1, max: 500 },
  minDistinctChars: { min: 1, max: 50 },
  /** Percentual que um único caractere pode ocupar (threshold 0.5–1). */
  repeatedPercent: { min: 50, max: 100 },
  sequence: { min: 3, max: 20 },
  blocklist: { items: 200, itemLength: 100 },
  message: 200,
} as const

/** Formato brasileiro: só em texto curto e um por campo. */
export const FORMAT_RULES = ['cpf', 'cnpj', 'phone_br', 'cep'] as const
export type FormatRule = (typeof FORMAT_RULES)[number]

/** Regras que o nível de rigor define (StrictnessPreset::rulesFor). */
export const ANTI_JUNK_RULES = [
  'no_only_symbols',
  'no_repeated_chars',
  'no_sequences',
  'min_distinct_chars',
  'blocklist',
  'min_words',
] as const satisfies readonly RuleType[]

export function isTextField(item: InputField): item is TextField {
  return item.type === 'short_text' || item.type === 'long_text'
}

export function findRule<T extends RuleType>(
  item: TextField,
  type: T,
): (Rule & { type: T }) | undefined {
  return item.rules?.find((rule): rule is Rule & { type: T } => rule.type === type)
}

/** Troca (ou acrescenta) a regra do tipo; `null` remove. A ordem das demais é mantida. */
export function setRule(item: TextField, type: RuleType, rule: Rule | null): void {
  const rules = item.rules ?? []
  const index = rules.findIndex((candidate) => candidate.type === type)

  if (rule === null) {
    if (index >= 0) rules.splice(index, 1)
  } else if (index >= 0) {
    rules[index] = rule
  } else {
    rules.push(rule)
  }

  item.rules = rules
}

export function formatOf(item: TextField): FormatRule | null {
  return item.rules?.map((rule) => rule.type).find(isFormatRule) ?? null
}

function isFormatRule(type: RuleType): type is FormatRule {
  return (FORMAT_RULES as readonly RuleType[]).includes(type)
}

export function setFormat(item: TextField, format: FormatRule | null): void {
  item.rules = (item.rules ?? []).filter((rule) => !isFormatRule(rule.type))

  if (format) {
    item.rules.push({ type: format })
  }
}

/**
 * Aplica um nível de rigor: tira as regras anti-lixo atuais e marca o preset.
 * O servidor acrescenta as regras do nível ao salvar (a do dono prevalece, por
 * isso as anteriores saem antes) e devolve o schema já sem `strictness`.
 */
export function applyStrictness(item: TextField, preset: Strictness): void {
  item.rules = (item.rules ?? []).filter(
    (rule) => !(ANTI_JUNK_RULES as readonly RuleType[]).includes(rule.type),
  )
  item.strictness = preset
}

/** Valores únicos e não vazios da lista de bloqueio, um por linha. */
export function parseBlocklist(text: string): string[] {
  const seen = new Set<string>()

  return text
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => {
      const key = line.toLocaleLowerCase('pt-BR')
      if (line === '' || seen.has(key)) return false
      seen.add(key)
      return true
    })
}
