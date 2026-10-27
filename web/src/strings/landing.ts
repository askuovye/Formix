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
  /** Cartão do hero: um formulário de exemplo que dá para responder (com um agradecimento). */
  mock: {
    label: 'Experimente: um formulário de exemplo',
    question: 'Qual é o seu principal objetivo com este formulário?',
    options: [
      {
        label: 'Aumentar vendas',
        reply: 'Anotado. Agora imagine os seus clientes respondendo assim.',
      },
      {
        label: 'Coletar feedback',
        reply: 'Feedback recebido, e sem nenhum “asdf” no meio.',
      },
      {
        label: 'Cadastrar-se',
        reply: 'Cadastro de mentirinha feito. O de verdade leva um minuto.',
      },
      { label: 'Outro', reply: 'Gostamos de quem foge do roteiro.' },
    ],
    send: 'Enviar resposta',
    thanks: 'Obrigado!',
    again: 'Responder de novo',
    leads: 'Novos leads',
    leadsPeriod: 'esta semana',
    leadsYou: 'contando com você',
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
