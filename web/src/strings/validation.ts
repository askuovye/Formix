import type { FileAccept } from '@/types/schema'

/** Mensagens do motor de validação das respostas (espelho das do back-end). */
export const validation = {
  required: 'Este campo é obrigatório.',
  text: 'Responda com um texto.',
  textMax: (max: number) => `Use no máximo ${max.toLocaleString('pt-BR')} caracteres.`,
  email: 'Informe um email válido.',
  number: 'Informe um número válido.',
  date: 'Informe uma data válida.',
  choice: 'Escolha uma das opções.',
  choices: 'Escolha entre as opções disponíveis.',
  integerRange: (min: number, max: number) => `Escolha um valor de ${min} a ${max}.`,
  rules: {
    /** Mesma mensagem para todas as heurísticas: não ensina a burlar (AGENTS.md §5.6). */
    heuristic: 'Esta resposta não parece válida. Revise e preencha com uma resposta de verdade.',
    minLength: (min: number) => `Use pelo menos ${min} caracteres.`,
    maxLength: (max: number) => `Use no máximo ${max} caracteres.`,
    minWords: (min: number) => `Escreva pelo menos ${min} palavras.`,
    cpf: 'Informe um CPF válido.',
    cnpj: 'Informe um CNPJ válido.',
    phoneBr: 'Informe um telefone válido com DDD.',
    cep: 'Informe um CEP válido.',
  },
  /** Anexos (espelho do FileAnswersValidator). */
  files: {
    tooMany: (max: number) =>
      max === 1 ? 'Envie no máximo 1 arquivo.' : `Envie no máximo ${max} arquivos.`,
    tooLarge: (name: string, max: number) => `O arquivo "${name}" passa do limite de ${max} MB.`,
    wrongType: (name: string, types: string) =>
      `O arquivo "${name}" não é de um tipo aceito. Envie: ${types}.`,
    duplicate: (name: string) => `O arquivo "${name}" já foi adicionado.`,
    totalTooLarge: (max: number) => `Os arquivos enviados somam mais de ${max} MB.`,
    categories: {
      pdf: 'PDF',
      image: 'imagem (JPG, PNG, WebP)',
      document: 'documento (DOC, DOCX, ODT)',
      spreadsheet: 'planilha (XLS, XLSX, ODS, CSV)',
      text: 'texto (TXT)',
    } satisfies Record<FileAccept, string>,
  },
} as const
