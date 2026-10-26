/** Textos compartilhados pelos componentes base. */
export const common = {
  required: 'obrigatório',
  loading: 'Carregando…',
  close: 'Fechar',
  cancel: 'Cancelar',
  selectPlaceholder: 'Selecione uma opção',
  tooManyAttempts: (seconds: number) =>
    `Muitas tentativas. Tente de novo em ${seconds} ${seconds === 1 ? 'segundo' : 'segundos'}.`,
} as const

/** Seletor de tema do app. */
export const theme = {
  label: 'Tema',
  system: 'Sistema',
  light: 'Claro',
  dark: 'Escuro',
} as const
