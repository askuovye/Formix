/** Página inicial (landing). Textos da referência visual (docs/design/reference.png). */
export const landing = {
  nav: {
    label: 'Principal',
    features: 'Recursos',
    github: 'GitHub',
    login: 'Entrar',
    register: 'Criar conta',
    panel: 'Ir para o painel',
  },
  hero: {
    title: ['Formulários', 'que se adaptam', 'ao seu jeito.'],
    text: 'Crie formulários personalizados, com lógica inteligente e um design que combina com a sua marca. Simples de usar, poderosos no resultado.',
    start: 'Começar agora',
    howItWorks: 'Ver como funciona',
  },
  /** Cartão ilustrativo do hero: um formulário de exemplo, sem interação. */
  mock: {
    label: 'Exemplo de formulário',
    question: 'Qual é o seu principal objetivo com este formulário?',
    options: ['Aumentar vendas', 'Coletar feedback', 'Cadastrar-se', 'Outro'],
    next: 'Próximo',
    leadsValue: '+12',
    leads: 'Novos leads',
    leadsPeriod: 'esta semana',
  },
  features: {
    title: 'Recursos',
    items: [
      {
        icon: 'square-pen',
        title: 'Editor intuitivo',
        text: 'Arraste e solte campos para criar formulários do seu jeito.',
      },
      {
        icon: 'git-branch',
        title: 'Lógica condicional',
        text: 'Mostre apenas o que importa para cada pessoa.',
      },
      {
        icon: 'chart-line',
        title: 'Análises em tempo real',
        text: 'Acompanhe resultados e tome decisões com dados.',
      },
      {
        icon: 'palette',
        title: 'Totalmente personalizável',
        text: 'Deixe tudo com a cara da sua marca, sem complicação.',
      },
      {
        icon: 'shield-check',
        title: 'Respostas de verdade',
        text: 'Barre o que é digitado só para passar, como “asdf” e “0000”.',
      },
    ],
  },
  showcase: {
    title: ['Tudo o que você precisa', 'em um só lugar.'],
    text: (brand: string) =>
      `Do editor à análise de resultados, o ${brand} te dá o controle total sobre seus formulários. Mais liberdade, mais resultado.`,
    more: 'Conheça todos os recursos',
    checklistTitle: 'Seu formulário, do seu jeito',
    checklist: [
      'Personalize o visual',
      'Adicione campos',
      'Configure a lógica',
      'Publique e acompanhe',
    ],
    statsLabel: 'Respostas',
    statsValue: 1248,
    statsTrend: '+12%',
    chart: 'Respostas subindo ao longo da semana (ilustração)',
  },
  footer: {
    label: 'Links do rodapé',
    terms: 'Termos de uso',
    privacy: 'Privacidade',
    github: 'GitHub',
    rights: (brand: string, year: number) => `© ${year} ${brand}`,
  },
} as const
