import type { FormStatus } from '@/types/api'

/** Lista "Meus formulários" e ações sobre um formulário. */
export const forms = {
  title: 'Meus formulários',
  subtitle: 'Gerencie seus formulários, veja as respostas e acompanhe o desempenho.',
  create: 'Novo formulário',
  tabsLabel: 'Filtrar por situação',
  tabs: { all: 'Todos', published: 'Ativos', draft: 'Rascunhos', closed: 'Arquivados' },
  status: {
    draft: 'Rascunho',
    published: 'Ativo',
    closed: 'Arquivado',
  } satisfies Record<FormStatus, string>,
  unpublishedChanges: 'Alterações não publicadas',
  submissions: (count: number) =>
    count === 0
      ? 'Nenhuma resposta'
      : count === 1
        ? '1 resposta'
        : `${count.toLocaleString('pt-BR')} respostas`,
  lastSubmission: (relative: string) => `Última ${relative}`,
  updated: (relative: string) => `Editado ${relative}`,
  actionsLabel: (title: string) => `Ações de "${title}"`,
  loading: 'Carregando formulários…',
  loadError: 'Não foi possível carregar seus formulários.',
  retry: 'Tentar de novo',
  loadMore: 'Carregar mais',
  search: 'Buscar formulários',
  searchPlaceholder: 'Título do formulário…',
  noResults: {
    title: 'Nenhum formulário encontrado',
    text: (term: string) => `Nada com "${term}" no título.`,
  },
  empty: {
    all: {
      title: 'Nenhum formulário ainda',
      text: 'Crie o primeiro e monte as perguntas no editor.',
    },
    published: {
      title: 'Nenhum formulário ativo',
      text: 'Formulários publicados, que estão recebendo respostas, aparecem aqui.',
    },
    draft: {
      title: 'Nenhum rascunho',
      text: 'Formulários ainda não publicados aparecem aqui.',
    },
    closed: {
      title: 'Nenhum formulário arquivado',
      text: 'Formulários arquivados param de receber respostas e aparecem aqui.',
    },
  },
  actions: {
    edit: 'Editar',
    submissions: 'Ver respostas',
    results: 'Ver resultados',
    export: 'Exportar respostas',
    copyLink: 'Copiar link',
    archive: 'Arquivar',
    reopen: 'Reabrir',
    delete: 'Excluir',
  },
  createDialog: {
    title: 'Novo formulário',
    description: 'Dê um nome; as perguntas você monta em seguida, no editor.',
    field: 'Nome do formulário',
    placeholder: 'Ex.: Inscrição para o evento',
    submit: 'Criar e editar',
    cancel: 'Cancelar',
  },
  confirm: {
    cancel: 'Cancelar',
    archive: {
      title: 'Arquivar formulário?',
      text: 'O formulário é fechado: o link público para de aceitar respostas. As respostas já recebidas continuam no painel e você pode reabrir quando quiser.',
      confirm: 'Arquivar',
    },
    reopen: {
      title: 'Reabrir formulário?',
      text: 'Este formulário tem alterações ainda não publicadas. Ao reabrir, elas também são publicadas.',
      confirm: 'Reabrir e publicar',
    },
    delete: {
      title: 'Excluir formulário?',
      text: (title: string) =>
        `"${title}" sai da sua lista e o link público para de funcionar. Esta ação não pode ser desfeita pelo painel.`,
      confirm: 'Excluir',
    },
  },
  toasts: {
    linkCopied: 'Link copiado',
    copyFailed: 'Não foi possível copiar. Link:',
    archived: 'Formulário arquivado',
    reopened: 'Formulário reaberto',
    deleted: 'Formulário excluído',
  },
  validation: {
    titleRequired: 'Dê um nome ao formulário.',
    titleMax: 'Use no máximo 200 caracteres.',
  },
} as const
