/** Textos das páginas (views). */
export const legal = {
  meta: (version: string, updatedAt: string) => `Versão ${version} · atualizada em ${updatedAt}`,
  loading: 'Carregando documento…',
  loadError: 'Não foi possível carregar o documento.',
  retry: 'Tentar de novo',
} as const

export const notFound = {
  code: '404',
  title: 'Página não encontrada',
  text: 'O endereço pode estar errado ou a página foi removida.',
  back: 'Voltar ao início',
} as const

export const settingsPage = {
  title: 'Configurações',
  subtitle: 'Dados da sua conta.',
  account: 'Conta',
  name: 'Nome',
  email: 'E-mail',
  verified: 'Confirmado',
  notVerified: 'Não confirmado',
} as const
