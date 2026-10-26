import type { FormAvailability } from '@/types/api'

/** Página pública do formulário (quem responde). */
export const renderer = {
  loading: 'Carregando formulário…',
  loadError: 'Não foi possível carregar o formulário.',
  retry: 'Tentar de novo',
  notFound: {
    title: 'Formulário não encontrado',
    text: 'O link pode estar errado ou o formulário foi removido.',
  },
  unavailable: {
    title: {
      open: '',
      closed: 'Formulário fechado',
      not_open_yet: 'Ainda não começou',
      ended: 'Prazo encerrado',
      limit_reached: 'Limite de respostas atingido',
    } satisfies Record<FormAvailability, string>,
    opensAt: (date: string) => `Abre em ${date}.`,
  },
  step: (current: number, total: number) => `Etapa ${current} de ${total}`,
  back: 'Voltar',
  next: 'Continuar',
  submit: 'Enviar resposta',
  submitting: 'Enviando…',
  submittingProgress: (percent: number) => `Enviando… ${percent}%`,
  privacy: 'Seus dados são tratados conforme a',
  privacyLink: 'Política de Privacidade',
  fixErrors: (count: number) =>
    count === 1 ? 'Corrija 1 campo para continuar.' : `Corrija ${count} campos para continuar.`,
  /** Erros do servidor que não pertencem a nenhum campo visível. */
  genericError: 'Não foi possível enviar a resposta. Revise os campos e tente de novo.',
  tooManyAttempts: (seconds: number) =>
    `Muitas tentativas seguidas. Tente de novo em ${seconds} segundos.`,
  choicePlaceholder: 'Selecione uma opção',
  rating: (value: number, max: number) => `${value} de ${max}`,
  ratingClear: 'Limpar nota',
  file: {
    drop: 'Arraste arquivos para cá ou',
    dropOne: 'Arraste um arquivo para cá ou',
    choose: 'escolha no aparelho',
    tap: 'Toque para escolher arquivos',
    tapOne: 'Toque para escolher um arquivo',
    limits: (types: string, maxMb: number, maxFiles: number) =>
      `${types} · até ${maxMb} MB ${maxFiles === 1 ? 'por arquivo' : `cada · até ${maxFiles} arquivos`}`,
    remove: (name: string) => `Remover ${name}`,
    added: (name: string) => `${name} adicionado.`,
    removed: (name: string) => `${name} removido.`,
    loading: 'Preparando o envio de arquivos…',
  },
  receipt: {
    title: 'Tudo certo!',
    edit: 'Editar minha resposta',
    editHint: (until: string) => `Você pode editar até ${until}. Guarde este link: ele é pessoal.`,
  },
  /** Rótulo do campo-armadilha (honeypot), só para leitores de tela de robôs. */
  honeypot: 'Website',
  madeWith: (brand: string) => `Feito com ${brand}`,
} as const
