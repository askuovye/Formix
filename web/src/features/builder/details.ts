import { builder } from '@/strings'
import type { Form, FormSettings } from '@/types/api'
import type { SchemaItem } from '@/types/schema'

/** Dados gerais do formulário editados no builder (fora do schema). */
export interface FormDetails {
  title: string
  description: string
  slug: string
  settings: FormSettings
}

/** Erros por caminho do servidor: `title`, `slug`, `settings.max_submissions`… */
export type DetailsErrors = Record<string, string>

/** Espelho de FormContentRules e FormSettings (back-end). */
export const DETAILS_LIMITS = {
  titleMax: 200,
  descriptionMax: 2000,
  slug: { min: 3, max: 80 },
  notificationEmails: 5,
  emailMax: 255,
  successMessageMax: 500,
  maxSubmissions: 1_000_000,
  retentionDays: 3650,
} as const

const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/
// Conferência básica; o servidor valida o formato completo (RFC).
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const e = builder.errors
const s = builder.settings

export function detailsFromForm(form: Form): FormDetails {
  return {
    title: form.title,
    description: form.description ?? '',
    slug: form.slug,
    // Cópia rasa basta (configurações são planas); structuredClone recusa o proxy do cache.
    settings: { ...form.settings, notification_emails: [...form.settings.notification_emails] },
  }
}

/** Corpo do PATCH. O endereço só vai enquanto ainda pode mudar. */
export function detailsPayload(details: FormDetails, slugEditable: boolean) {
  return {
    title: details.title,
    description: details.description.trim() === '' ? null : details.description,
    ...(slugEditable ? { slug: details.slug } : {}),
    settings: details.settings,
  }
}

function intBetween(value: number | null, min: number, max: number): boolean {
  return value === null || (Number.isInteger(value) && value >= min && value <= max)
}

/** Campos `email` do formulário: candidatos a receber a cópia da resposta. */
export function emailFields(items: readonly SchemaItem[]) {
  return items.flatMap((item) => (item.kind === 'input' && item.type === 'email' ? [item] : []))
}

/** Valida como o back-end faria (o servidor continua sendo a fonte da verdade). */
export function validateDetails(
  details: FormDetails,
  items: readonly SchemaItem[],
  slugEditable: boolean,
): DetailsErrors {
  const errors: DetailsErrors = {}
  const settings = details.settings

  if (details.title.trim() === '') errors.title = e.required
  else if (details.title.length > DETAILS_LIMITS.titleMax) {
    errors.title = e.max(DETAILS_LIMITS.titleMax)
  }

  if (details.description.length > DETAILS_LIMITS.descriptionMax) {
    errors.description = e.max(DETAILS_LIMITS.descriptionMax)
  }

  if (slugEditable) {
    const { min, max } = DETAILS_LIMITS.slug
    const slug = details.slug
    if (slug.length < min || slug.length > max || !SLUG_PATTERN.test(slug)) errors.slug = e.slug
  }

  const emails = settings.notification_emails
  const seen = new Set<string>()
  if (emails.length > DETAILS_LIMITS.notificationEmails) {
    errors['settings.notification_emails'] = s.notificationEmailsMax(
      DETAILS_LIMITS.notificationEmails,
    )
  } else {
    for (const email of emails) {
      const key = email.toLowerCase()
      if (email.length > DETAILS_LIMITS.emailMax || !EMAIL_PATTERN.test(email)) {
        errors['settings.notification_emails'] = e.email
        break
      }
      if (seen.has(key)) {
        errors['settings.notification_emails'] = e.emailRepeated
        break
      }
      seen.add(key)
    }
  }

  if ((settings.success_message?.length ?? 0) > DETAILS_LIMITS.successMessageMax) {
    errors['settings.success_message'] = e.max(DETAILS_LIMITS.successMessageMax)
  }

  if (!intBetween(settings.max_submissions, 1, DETAILS_LIMITS.maxSubmissions)) {
    errors['settings.max_submissions'] = e.intBetween(1, DETAILS_LIMITS.maxSubmissions)
  }

  if (!intBetween(settings.retention_days, 1, DETAILS_LIMITS.retentionDays)) {
    errors['settings.retention_days'] = e.intBetween(1, DETAILS_LIMITS.retentionDays)
  }

  if (
    settings.opens_at &&
    settings.closes_at &&
    Date.parse(settings.closes_at) <= Date.parse(settings.opens_at)
  ) {
    errors['settings.closes_at'] = s.closesBeforeOpens
  }

  if (
    settings.send_copy_to_respondent &&
    !emailFields(items).some((field) => field.id === settings.respondent_email_field)
  ) {
    errors['settings.respondent_email_field'] = s.respondentEmailFieldRequired
  }

  return errors
}

/**
 * Erros do 422 que não são do schema, com `settings.notification_emails.2`
 * agrupado no campo da lista.
 */
export function detailsServerErrors(fieldErrors: Record<string, string[]>): DetailsErrors {
  const errors: DetailsErrors = {}

  for (const [path, messages] of Object.entries(fieldErrors)) {
    const message = messages[0]
    if (!message || path === 'schema' || path.startsWith('schema.')) continue

    const key = path.replace(/^(settings\.notification_emails)\.\d+$/, '$1')
    errors[key] ??= message
  }

  return errors
}

/** ISO (UTC) → valor de `<input type="datetime-local">` no fuso do navegador. */
export function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')

  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    `T${pad(date.getHours())}:${pad(date.getMinutes())}`
  )
}

/** Valor de `datetime-local` (fuso do navegador) → ISO em UTC; vazio → null. */
export function fromLocalInput(value: string): string | null {
  if (value === '') return null
  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? null : date.toISOString()
}
