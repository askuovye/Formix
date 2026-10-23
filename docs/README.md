# Wiki do projeto

Índice da documentação. As regras gerais ficam no [`AGENTS.md`](../AGENTS.md); aqui fica o detalhe, consultado sob demanda.

| Documento | Quando ler |
|---|---|
| [retomar.md](retomar.md) | **Ao começar uma sessão**: onde paramos, próximo passo, instalar o Docker e testar |
| [decisions.md](decisions.md) | Antes de mudar arquitetura, stack ou infraestrutura; para entender o porquê de algo |
| [barreiras.md](barreiras.md) | Antes de planejar uma fase ou o lançamento; funcionalidades e riscos pendentes |
| [database.md](database.md) | Antes de criar ou alterar migrations; esquema atual e histórico |
| [../fixtures/README.md](../fixtures/README.md) | Antes de mexer no motor de validação (back ou front); formato dos casos compartilhados |
| [../api/README.md](../api/README.md) | Subir o ambiente, rodar testes e filas |
| [api.md](api.md) | Contrato da API para o front: endpoints, payloads, erros e rotas que a SPA precisa ter |
| [scaling.md](scaling.md) | Antes de mudar de hospedagem ou quando o volume crescer; gargalos e degraus de escala |
| [security.md](security.md) | Antes de lançar e a cada funcionalidade sensível; checklist de segurança e OWASP com status |

## Como manter

- Decisão nova → entrada no fim de `decisions.md` (D17, D18…), curta: decisão, porquê, consequências.
- Migration nova → linha no histórico de `database.md` e tabela atualizada.
- Funcionalidade adiada ou risco conhecido → linha em `barreiras.md` (B10, B11…); ao resolver, remover a linha e citar a decisão.
- O `AGENTS.md` só recebe regras que valem para todo o código; detalhes e justificativas ficam aqui.
