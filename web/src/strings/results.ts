const count = (value: number) => value.toLocaleString('pt-BR')

/** Aba de resultados (gráficos) do painel de respostas. */
export const results = {
  tabs: { label: 'Visualização', submissions: 'Respostas', results: 'Resultados' },
  needsForm: 'Escolha um formulário no filtro para ver os resultados.',
  loading: 'Calculando resultados…',
  loadError: 'Não foi possível calcular os resultados.',
  retry: 'Tentar de novo',
  empty: {
    title: 'Nada para mostrar ainda',
    text: 'Quando chegarem respostas com estes filtros, os gráficos aparecem aqui.',
  },
  stats: { total: 'Respostas', lastWeek: 'Últimos 7 dias', edited: 'Editadas' },
  timeline: {
    title: { day: 'Respostas por dia', week: 'Respostas por semana' },
    bar: (date: string, value: number) =>
      `${date}: ${value === 1 ? '1 resposta' : `${count(value)} respostas`}`,
    week: (date: string) => `semana de ${date}`,
    peak: (value: number) => `máx. ${count(value)}`,
  },
  answered: (answered: number, total: number) =>
    `${count(answered)} de ${count(total)} responderam`,
  average: 'Média',
  min: 'Menor',
  max: 'Maior',
  earliest: 'Mais antiga',
  latestDate: 'Mais recente',
  latestTexts: 'Últimas respostas',
  openSubmission: (value: string) => `Abrir a resposta "${value}"`,
  files: (value: number) =>
    value === 1 ? '1 resposta com arquivo' : `${count(value)} respostas com arquivo`,
  noAnswers: 'Ninguém respondeu este campo.',
  option: (label: string, value: number, percent: string) =>
    `${label}: ${value === 1 ? '1 resposta' : `${count(value)} respostas`} (${percent})`,
  table: 'Dados do gráfico',
}
