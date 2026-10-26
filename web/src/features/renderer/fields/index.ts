import type { Component } from 'vue'

import type { InputType } from '@/types/schema'

import FileAnswer from './FileAnswer.vue'
import LongTextAnswer from './LongTextAnswer.vue'
import MultipleChoiceAnswer from './MultipleChoiceAnswer.vue'
import RatingAnswer from './RatingAnswer.vue'
import ScaleAnswer from './ScaleAnswer.vue'
import SingleChoiceAnswer from './SingleChoiceAnswer.vue'
import TextAnswer from './TextAnswer.vue'

/**
 * Um componente por tipo de campo (AGENTS.md §7). Tipo novo = componente +
 * registro aqui + tipo no schema + regra no back-end. Todos recebem
 * `field`, `error` e `v-model` com o valor da resposta.
 */
export const fieldComponents: Record<InputType, Component> = {
  short_text: TextAnswer,
  email: TextAnswer,
  number: TextAnswer,
  date: TextAnswer,
  long_text: LongTextAnswer,
  single_choice: SingleChoiceAnswer,
  multiple_choice: MultipleChoiceAnswer,
  rating: RatingAnswer,
  scale: ScaleAnswer,
  file: FileAnswer,
}
