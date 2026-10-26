/** Estrutura do app: shell do painel, layout público e avisos. */
export const shell = {
  skipToContent: 'Pular para o conteúdo',
  nav: {
    label: 'Navegação principal',
    forms: 'Formulários',
    settings: 'Configurações',
  },
  openMenu: 'Abrir menu',
  accountMenu: 'Menu da conta',
  logout: 'Sair',
  loggedOut: 'Você saiu da sua conta.',
  sessionExpired: 'Sua sessão expirou. Entre novamente.',
  verifyEmail: {
    text: 'Confirme seu e-mail para publicar formulários.',
    action: 'Reenviar e-mail',
    sent: 'E-mail de confirmação reenviado',
  },
  footer: {
    terms: 'Termos de Uso',
    privacy: 'Política de Privacidade',
  },
  toasts: {
    region: 'Avisos',
  },
} as const

/** Títulos da aba do navegador, por rota. */
export const pageTitles = {
  forms: 'Formulários',
  formEditor: 'Editor',
  settings: 'Configurações',
  terms: 'Termos de Uso',
  privacy: 'Política de Privacidade',
  notFound: 'Página não encontrada',
  login: 'Entrar',
  register: 'Criar conta',
  forgotPassword: 'Esqueci minha senha',
  resetPassword: 'Nova senha',
  verifyEmail: 'Confirmação de e-mail',
  styleguide: 'Styleguide',
} as const
