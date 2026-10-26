import * as v from 'valibot'

import { forms } from '@/strings'

/** Espelho de FormContentRules::TITLE no back-end. */
export const FORM_TITLE_MAX_LENGTH = 200

export const createFormSchema = v.object({
  title: v.pipe(
    v.string(),
    v.trim(),
    v.nonEmpty(forms.validation.titleRequired),
    v.maxLength(FORM_TITLE_MAX_LENGTH, forms.validation.titleMax),
  ),
})
