import { builder } from '@/strings'
import { SCHEMA_LIMITS, type InputField, type SchemaItem } from '@/types/schema'

import { RULE_LIMITS, isTextField } from './rules'
import { VISIBILITY_LIMITS, countConditions, visibilityError } from './visibility'

/**
 * Erros de um item por caminho dentro dele: `label`, `options.0.label`,
 * `scale.max`, `rules.min_length`, `rules.blocklist.message`, `item`…
 * Regras são identificadas pelo tipo (não pela posição) para sobreviver à
 * reordenação.
 */
export type ItemErrors = Record<string, string>

/** Erros que não pertencem a um item (ex.: limite de campos do formulário). */
export const GENERAL_ERRORS = '*'

/** Espelho dos limites de FormSchemaRules (back-end) usados pelo builder. */
export const ITEM_LIMITS = {
  options: { min: 1, max: 100, labelMax: 200 },
  file: { maxFiles: 10, maxSizeMb: 10 },
  rating: { min: 3, max: 10 },
  scale: { lowest: 0, highest: 10, labelMax: 40 },
} as const

const e = builder.errors

function isInt(value: unknown): value is number {
  return typeof value === 'number' && Number.isInteger(value)
}

function between(value: unknown, min: number, max: number): boolean {
  return typeof value === 'number' && value >= min && value <= max
}

function inputErrors(item: InputField, errors: ItemErrors): void {
  if (item.label.trim() === '') errors.label = e.required
  else if (item.label.length > SCHEMA_LIMITS.labelMax) errors.label = e.max(SCHEMA_LIMITS.labelMax)

  if ((item.help_text?.length ?? 0) > SCHEMA_LIMITS.helpTextMax) {
    errors.help_text = e.max(SCHEMA_LIMITS.helpTextMax)
  }

  if ('placeholder' in item && (item.placeholder?.length ?? 0) > SCHEMA_LIMITS.placeholderMax) {
    errors.placeholder = e.max(SCHEMA_LIMITS.placeholderMax)
  }

  switch (item.type) {
    case 'single_choice':
    case 'multiple_choice': {
      const { min, max, labelMax } = ITEM_LIMITS.options
      if (item.options.length < min) errors.options = e.optionsMin
      if (item.options.length > max) errors.options = e.optionsMax(max)
      item.options.forEach((option, index) => {
        if (option.label.trim() === '') errors[`options.${index}.label`] = e.required
        else if (option.label.length > labelMax) errors[`options.${index}.label`] = e.max(labelMax)
      })
      break
    }
    case 'file': {
      const { maxFiles, maxSizeMb } = ITEM_LIMITS.file
      if (item.file.accept.length === 0) errors['file.accept'] = e.acceptMin
      if (!isInt(item.file.max_files) || !between(item.file.max_files, 1, maxFiles)) {
        errors['file.max_files'] = e.intBetween(1, maxFiles)
      }
      if (!isInt(item.file.max_size_mb) || !between(item.file.max_size_mb, 1, maxSizeMb)) {
        errors['file.max_size_mb'] = e.intBetween(1, maxSizeMb)
      }
      break
    }
    case 'rating': {
      const { min, max } = ITEM_LIMITS.rating
      if (!isInt(item.rating.max) || !between(item.rating.max, min, max)) {
        errors['rating.max'] = e.intBetween(min, max)
      }
      break
    }
    case 'scale': {
      const { lowest, highest, labelMax } = ITEM_LIMITS.scale
      const { min, max, min_label: minLabel, max_label: maxLabel } = item.scale
      if (!isInt(min) || !between(min, lowest, highest - 1)) {
        errors['scale.min'] = e.intBetween(lowest, highest - 1)
      }
      if (!isInt(max) || !between(max, lowest, highest)) {
        errors['scale.max'] = e.intBetween(lowest, highest)
      } else if (isInt(min) && max <= min) {
        errors['scale.max'] = e.scaleOrder
      }
      if ((minLabel?.length ?? 0) > labelMax) errors['scale.min_label'] = e.max(labelMax)
      if ((maxLabel?.length ?? 0) > labelMax) errors['scale.max_label'] = e.max(labelMax)
      break
    }
    default:
      break
  }

  if (isTextField(item)) {
    for (const rule of item.rules ?? []) {
      const key = `rules.${rule.type}`

      if ((rule.message?.length ?? 0) > RULE_LIMITS.message) {
        errors[`${key}.message`] = e.max(RULE_LIMITS.message)
      }

      switch (rule.type) {
        case 'min_length':
        case 'max_length':
          if (
            !isInt(rule.value) ||
            !between(rule.value, RULE_LIMITS.length.min, RULE_LIMITS.length.max)
          ) {
            errors[key] = e.intBetween(RULE_LIMITS.length.min, RULE_LIMITS.length.max)
          }
          break
        case 'min_words':
          if (
            !isInt(rule.value) ||
            !between(rule.value, RULE_LIMITS.minWords.min, RULE_LIMITS.minWords.max)
          ) {
            errors[key] = e.intBetween(RULE_LIMITS.minWords.min, RULE_LIMITS.minWords.max)
          }
          break
        case 'min_distinct_chars': {
          const { min, max } = RULE_LIMITS.minDistinctChars
          if (!isInt(rule.value) || !between(rule.value, min, max))
            errors[key] = e.intBetween(min, max)
          break
        }
        case 'no_repeated_chars':
          if (!between(rule.threshold, 0.5, 1)) {
            errors[key] = e.intBetween(
              RULE_LIMITS.repeatedPercent.min,
              RULE_LIMITS.repeatedPercent.max,
            )
          }
          break
        case 'no_sequences': {
          const { min, max } = RULE_LIMITS.sequence
          if (rule.value !== undefined && (!isInt(rule.value) || !between(rule.value, min, max))) {
            errors[key] = e.intBetween(min, max)
          }
          break
        }
        case 'blocklist': {
          const { items, itemLength } = RULE_LIMITS.blocklist
          if (rule.values.length === 0) errors[key] = e.blocklistEmpty
          else if (rule.values.length > items) errors[key] = e.blocklistMax(items)
          else if (rule.values.some((value) => value.length > itemLength)) {
            errors[key] = e.blocklistItemMax(itemLength)
          }
          break
        }
        default:
          break
      }
    }
  }
}

/** Valida os itens como o back-end faria (o servidor continua sendo a fonte da verdade). */
export function validateItems(items: readonly SchemaItem[]): Map<string, ItemErrors> {
  const result = new Map<string, ItemErrors>()

  items.forEach((item, index) => {
    const errors: ItemErrors = {}

    if (item.kind === 'input') {
      inputErrors(item, errors)
      const visibility = visibilityError(items, index)
      if (visibility) errors.visibility = visibility
    } else if (item.type === 'section_title' || item.type === 'text') {
      const max = item.type === 'text' ? SCHEMA_LIMITS.textBlockMax : SCHEMA_LIMITS.sectionTitleMax
      if (item.text.trim() === '') errors.text = e.required
      else if (item.text.length > max) errors.text = e.max(max)
    } else if (item.type === 'page_break') {
      const previous = items[index - 1]
      if (index === 0 || index === items.length - 1 || previous?.type === 'page_break') {
        errors.item = e.pageBreakPlacement
      }
    }

    if (Object.keys(errors).length > 0) {
      result.set(item.id, errors)
    }
  })

  if (countConditions(items) > VISIBILITY_LIMITS.maxRules) {
    result.set(GENERAL_ERRORS, {
      conditions: builder.logic.errors.tooMany(VISIBILITY_LIMITS.maxRules),
    })
  }

  return result
}

const SERVER_PATH = /^schema\.fields\.(\d+)\.(.+)$/

/**
 * Converte os erros do 422 (`schema.fields.2.rules.1.value`) para o formato do
 * builder, usando os itens que foram enviados para achar o id e o tipo da regra.
 */
export function mapServerErrors(
  fieldErrors: Record<string, string[]>,
  sent: readonly SchemaItem[],
): Map<string, ItemErrors> {
  const result = new Map<string, ItemErrors>()
  const add = (id: string, key: string, message: string) => {
    const errors = result.get(id) ?? {}
    errors[key] ??= message
    result.set(id, errors)
  }

  for (const [path, messages] of Object.entries(fieldErrors)) {
    const message = messages[0]
    if (!message) continue

    const match = SERVER_PATH.exec(path)
    const item = match ? sent[Number(match[1])] : undefined

    if (!match || !item) {
      add(GENERAL_ERRORS, path, message)
      continue
    }

    let key = match[2] ?? 'item'
    const ruleMatch = /^rules\.(\d+)(?:\.(.+))?$/.exec(key)

    if (ruleMatch && item.kind === 'input' && isTextField(item)) {
      const rule = item.rules?.[Number(ruleMatch[1])]
      // Parâmetros (value, threshold, values.N) ficam no próprio campo da regra.
      const suffix = ruleMatch[2] === 'message' ? '.message' : ''
      key = rule ? `rules.${rule.type}${suffix}` : 'rules'
    } else if (/^options\.\d+\.value$/.test(key)) {
      // O valor da opção é gerado pelo builder; o erro aparece no rótulo.
      key = key.replace(/value$/, 'label')
    } else if (key === 'type' || key === 'id' || key === 'kind') {
      key = 'item'
    }

    add(item.id, key, message)
  }

  return result
}
