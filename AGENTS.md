# AGENTS.md

Guia para agentes de IA e desenvolvedores que trabalham neste repositório.
Leia este arquivo inteiro antes de alterar qualquer código. Em caso de conflito entre este arquivo e uma preferência pessoal, **este arquivo vence**; se achar que uma regra está errada, proponha a mudança aqui, não a ignore.

---

## 1. Visão do produto

Ferramenta online para **construção de formulários** com foco em **controle** sobre como o formulário é criado e respondido.

Requisitos centrais:

1. **Builder**: o dono monta o formulário (campos, ordem, regras, validações).
2. **Renderer público**: página pública, acessada por link, onde o respondente preenche.
3. **Cópia da resposta por email** para o dono do formulário.
4. **Anexos**: campos de upload de arquivo nas respostas.
5. **Limitação de respostas inválidas**: bloquear lixo digitado só para passar (`0000`, `xxxxx`, `......`, `asdf`, etc.).

O **motor de validação anti-lixo** é o coração do produto. Trate-o como código crítico.

---

## 2. Stack (fechada)

Não adicione, troque ou remova tecnologias sem aprovação explícita do responsável pelo projeto.

### Backend (`/api`)

| Item | Tecnologia |
|---|---|
| Framework | Laravel (versão estável mais recente suportada) |
| Banco | **MariaDB/MySQL** (colunas `json` para schema e respostas) |
| Fila, cache e sessão | Driver **database**; fila processada por **cron** (`schedule:run` a cada minuto) |
| Autenticação | **Laravel Sanctum** (modo SPA: cookies + CSRF) |
| Anexos | **Spatie Laravel MediaLibrary** |
| Validação BR | **LaravelLegends/pt-br-validator** |
| Email | **SMTP** de qualquer provedor (ex.: Brevo, plano grátis), sempre via fila; Mailpit no ambiente local |
| Armazenamento | Disco local **privado** no servidor; `league/flysystem-aws-s3-v3` para migrar a S3-compatível |
| Testes | **Pest** + **Larastan** (nível 8) |

### Frontend (`/web`)

| Item | Tecnologia |
|---|---|
| Framework | **Vue 3** (Composition API, `<script setup lang="ts">`) |
| Build | **Vite** |
| Linguagem | **TypeScript** (modo `strict`) |
| Estado global | **Pinia** |
| Rotas | **Vue Router** |
| Dados do servidor | **TanStack Query (Vue Query)** |
| HTTP | **Axios** |
| Validação | **Valibot** |
| Drag and drop | **vuedraggable v4 (SortableJS)**, só no builder |
| Upload | **Uppy** |
| Estilos | **SCSS** (Dart Sass, `sass-embedded`; só `@use`/`@forward`) + CSS custom properties |
| Primitivas de UI | **Reka UI** (headless, sem estilos) |
| Ícones | **Iconify offline** (`@iconify/vue` + coleção empacotada `@iconify-json/lucide`); sem chamadas à API do Iconify em runtime |
| Tipografia | **Inter** (Regular, Medium, Semibold, Bold) hospedada localmente, sem CDN |
| Animação de interface | **Motion for Vue** (`motion-v`): componentes, presença, layout e estados |
| Animação orquestrada | **GSAP**: sequências em timeline, contadores e gráficos |
| Testes | **Vitest** |

### Fora do escopo por enquanto (NÃO adicionar)

- Redis, Horizon e antivírus (ClamAV): indisponíveis na hospedagem compartilhada; voltam se migrar para VPS (decisão D14).
- Tailwind CSS ou qualquer framework/biblioteca de CSS utilitário.
- Bibliotecas de componentes de UI **estilizadas** (PrimeVue, Vuetify, shadcn-vue, etc.). Reka UI é permitida por ser headless.
- Outras bibliotecas de animação ou de ícones além de Motion, GSAP e Iconify.
- Playwright / testes E2E.
- Inertia.js (a arquitetura é SPA + API separada).
- FormKit ou qualquer biblioteca de renderização de formulários: o renderer é **próprio**, orientado a schema JSON.
- vue-i18n: os textos da interface ficam num módulo próprio (`strings/`).

Estilização: **SCSS** com arquivos globais em `styles/` e `<style scoped lang="scss">` nos componentes. Cores, espaçamentos, raios, sombras, tipografia, durações e easings são **CSS custom properties** (design tokens, trocáveis em runtime); o SCSS fica para estrutura, mixins, funções e organização. Nomenclatura **BEM**, sem `!important`, sem seletores profundos e sem valores soltos (tudo vem de tokens).

---

## 3. Arquitetura geral

```
/
├── AGENTS.md
├── api/        # Laravel (API JSON)
└── web/        # Vue 3 SPA
```

- Monorepo com dois projetos independentes (`api` e `web`), cada um com seu próprio gerenciador de dependências, testes e CI.
- A comunicação é **somente via API HTTP JSON**. O front nunca acessa o banco, e o back nunca renderiza HTML de aplicação (exceto emails).
- Front e API devem compartilhar o domínio principal para o Sanctum em modo SPA (ex.: `app.exemplo.com` e `api.exemplo.com`).

### Fluxos principais

1. **Dono**: autentica (Sanctum) → cria/edita formulário no builder → publica → recebe email a cada resposta → consulta respostas no painel.
2. **Respondente** (sem login): abre o link público → renderer monta o formulário a partir do schema → valida no cliente (Valibot) → envia → backend revalida (fonte da verdade) → grava → enfileira email.
3. **Edição pelo respondente** (se o dono ativar no formulário): link secreto de edição válido por **4 horas** após o envio; cada edição guarda a versão anterior e notifica o dono. Bloqueada se o formulário for fechado.

### Princípio-chave: backend é a fonte da verdade

- Toda validação **deve existir no backend**. A validação do front é apenas conveniência de UX.
- O front **nunca** é considerado confiável (nem o schema que ele envia, nem os arquivos).
- O schema do formulário é carregado do **banco**, nunca aceito do cliente no momento do envio da resposta.

---

## 4. Modelo de dados (núcleo)

| Tabela | Campos principais |
|---|---|
| `users` | dados do dono do formulário |
| `forms` | `id`, `user_id`, `title`, `slug` (único), `status` (`draft`/`published`/`closed`), `schema` (json), `schema_version`, `settings` (json: email de destino, limite de respostas, mensagem de sucesso, etc.) |
| `form_versions` | `id`, `form_id`, `version`, `schema` (json): snapshot imutável a cada publicação |
| `submissions` | `id`, `form_id`, `schema_version`, `data` (json), `ip_hash`, `user_agent`, `created_at` |
| `submission_revisions` | versões anteriores de uma resposta editada pelo respondente |
| `legal_acceptances` | aceite de Termos/Política por versão (prova LGPD), gravado no cadastro |
| (media) | tabela do Spatie MediaLibrary, associada a `submissions` por coleção por campo |

Regras:

- Uma resposta guarda a `schema_version` com que foi enviada. **Nunca** altere o schema de uma versão publicada; publique uma nova versão.
- Use **UUID/ULID** como identificador público (nunca expor IDs sequenciais em URLs públicas).
- IP: armazenar apenas **hash** (com sal), nunca o IP puro (LGPD).
- Use `SoftDeletes` em `forms`; respostas só são removidas por ação explícita do dono ou política de retenção.
- Toda alteração de tabela é registrada em `docs/database.md` (fonte de verdade do esquema atual).

---

## 5. Schema do formulário e regras de validação

O formulário é descrito por um **schema JSON versionado** (o número da versão fica em `forms.schema_version`, não dentro do JSON). Formato completo em `docs/api.md`. Exemplo:

```json
{
  "fields": [
    {
      "id": "nome",
      "type": "text",
      "label": "Nome completo",
      "required": true,
      "rules": [
        { "type": "min_length", "value": 5 },
        { "type": "no_repeated_chars", "threshold": 0.8 },
        { "type": "min_words", "value": 2 }
      ]
    }
  ]
}
```

### Catálogo inicial de regras (anti-lixo e BR)

| Regra | Descrição |
|---|---|
| `no_repeated_chars` | rejeita texto em que um único caractere ocupa ≥ `threshold` do total (`aaaaa`, `0000`, `.....`) |
| `min_distinct_chars` | exige um mínimo de caracteres distintos |
| `no_sequences` | rejeita sequências óbvias (`12345`, `abcde`, `qwerty`) |
| `no_only_symbols` | rejeita texto só de pontuação/símbolos |
| `blocklist` | lista de valores bloqueados (`teste`, `asdf`, `n/a`, `xxx`), editável pelo dono |
| `min_words` | mínimo de palavras em texto longo |
| `cpf` / `cnpj` | dígito verificador; rejeita sequências iguais (`111.111.111-11`) |
| `phone_br` / `cep` | formato brasileiro |
| `min_length` / `max_length` | limites de caracteres (após normalização) |
| Regras de tipo | `email`, `number`, `date` e escolha (valor deve estar entre as opções), aplicadas pelo tipo do campo |

**Rigor** (`leve`/`médio`/`estrito`) é um atalho do builder: é expandido em regras explícitas **ao salvar**, e o schema guardado nunca contém o preset.

Regras de projeto:

1. **Cada regra é um objeto JSON** com `type` + parâmetros. Sem código executável no schema.
2. **Uma implementação por lado, mesmo contrato**: PHP no backend (fonte da verdade) e TypeScript/Valibot no frontend (espelho).
3. As duas implementações **devem passar pela mesma bateria de casos de teste** (arquivo de fixtures JSON compartilhado em `/fixtures/validation-cases.json`, lido por Pest e por Vitest; formato e definições em `fixtures/README.md`).
4. Toda regra nova exige: implementação nos dois lados, fixtures com casos válidos **e** inválidos, mensagem de erro padrão em português e possibilidade de mensagem customizada por campo.
5. Normalize a entrada antes de validar (trim, normalização Unicode, remoção de espaços duplicados quando fizer sentido). Documente a normalização na regra.
6. Mensagens de erro devem ser claras para o respondente, sem expor detalhes internos da heurística.
7. Para CPF/CNPJ/telefone/CEP no backend, **use as regras do `LaravelLegends/pt-br-validator`**; não reimplemente.

---

## 6. Convenções do Backend (Laravel)

### Estrutura e organização

- Siga as convenções do Laravel e a **PSR-12**. Formatação automática com **Laravel Pint** (preset `laravel`).
- **Controllers finos**: validam (via Form Request), delegam e retornam Resource. Sem regra de negócio.
- Regra de negócio em **Actions/Services** (`app/Actions` ou `app/Services`), classes pequenas, de responsabilidade única, injetadas por container.
- Validação de entrada **sempre** em **Form Requests**. Regras anti-lixo implementadas como **Rule objects** (`app/Rules`) ou, para as dinâmicas do schema, via um `FormSubmissionValidator` que traduz o schema em regras.
- Respostas da API **sempre** via **API Resources**. Nunca retornar models crus.
- Autorização via **Policies** (`FormPolicy`, etc.). Nada de checagem manual de `user_id` espalhada em controllers.
- Use **Enums** nativos do PHP para status e tipos (`FormStatus`, `FieldType`, `RuleType`).
- Tipagem estrita: `declare(strict_types=1);` em arquivos novos, type hints e return types em tudo.
- Models com `$fillable` explícito (nunca `$guarded = []`), `casts()` para JSON/enums/datas.
- Evite N+1: use `with()`/`load()` e ative `Model::preventLazyLoading()` fora de produção.
- Migrations são imutáveis depois de mergeadas; mudanças vão em migrations novas. Índices e chaves estrangeiras sempre explícitos.
- Configuração via `config/*.php` + `.env`. **Nunca** usar `env()` fora dos arquivos de config.
- Rotas da API versionadas: prefixo `/api/v1`.

### Filas

- Todo trabalho lento ou externo (envio de email, exportação, processamento de anexos) roda em **Jobs** na fila, nunca na requisição.
- Jobs devem ser **idempotentes**, ter `tries`, `backoff` e `failed()` tratados.
- Filas por prioridade: `emails`, `default` e `exports` (conexão `database-long`). Workers agendados em `routes/console.php`.
- Sem `pcntl` na hospedagem, o `timeout` do job não é imposto: jobs devem ser curtos e processar em lotes.
- Não use recursos exclusivos de Redis/PostgreSQL; consultas por chave dentro de colunas `json` só com justificativa.

### Email

- Use **Mailables** com views Blade em **Markdown mail** ou HTML simples; envio via `ShouldQueue`.
- O email ao dono contém as respostas formatadas e **links assinados temporários** para os anexos. **Não** anexar arquivos diretamente ao email.
- Escape sempre o conteúdo vindo do respondente (é entrada não confiável).
- Configure SPF/DKIM/DMARC no domínio de envio.

### Anexos (MediaLibrary)

- Cada campo de arquivo vira uma **coleção** do MediaLibrary associada à `Submission`.
- Disco **privado** (nunca `public`). Download somente por **URL temporária assinada** ou rota autenticada com Policy.
- Validar por campo: **tipos permitidos (MIME real, não só extensão)**, **tamanho máximo**, **quantidade máxima**.
- **Sem antivírus por enquanto**: compense com allowlist estrita de MIME (nunca executáveis, scripts ou HTML/SVG), download sempre como `attachment` com `nosniff`, e nunca processar o conteúdo (sem conversões de imagem).
- Nome original é armazenado como metadado; o nome em disco é gerado (UUID). Nunca confiar no nome enviado pelo cliente.

### Segurança

- **Sanctum em modo SPA**: cookies httpOnly, CSRF ativo, `SESSION_DOMAIN` e `SANCTUM_STATEFUL_DOMAINS` corretos.
- **Rate limiting** (`RateLimiter`) nas rotas públicas de envio de resposta e de autenticação.
- Proteção anti-bot nas rotas públicas: honeypot e limite por IP; CAPTCHA pode ser adicionado depois (decisão futura).
- Limite de tamanho de payload e de número de campos por formulário.
- Nunca confie em IDs vindos do cliente: sempre resolva pelo `slug`/ULID e verifique `status = published`.
- LGPD: coletar o mínimo, hash de IP, política de retenção configurável, exclusão de dados sob solicitação.
- Não logar dados de respostas, tokens ou conteúdo de arquivos.

### Testes (Pest)

- Todo código novo de backend vem com testes. Meta mínima: **feature tests** para cada endpoint e **unit tests** para cada regra de validação e Action.
- Testes de validação consomem `/fixtures/validation-cases.json`.
- Use `RefreshDatabase`, factories e `Mail::fake()`, `Queue::fake()`, `Storage::fake()`.
- Teste os caminhos de erro (resposta inválida, formulário fechado, arquivo malicioso, arquivo grande demais), não só o caminho feliz.

---

## 7. Convenções do Frontend (Vue 3 + TypeScript)

### Estrutura

```
web/src/
├── app/              # bootstrap: main.ts, router, providers (query client, pinia)
├── api/              # cliente Axios, endpoints tipados, funções de acesso à API
├── features/
│   ├── auth/
│   ├── builder/      # editor de formulário (drag and drop, painel de propriedades)
│   ├── renderer/     # renderização pública do formulário a partir do schema
│   ├── submissions/  # painel de respostas
│   └── validation/   # motor de regras (espelho do backend) com Valibot
├── components/       # componentes base próprios (AppButton, AppIcon, AppDialog…), sobre Reka UI quando houver primitiva
├── composables/      # composables genéricos (useXxx), incl. useGsapContext
├── config/           # constantes de configuração (nome da marca, flags como VITE_USE_MOCKS)
├── motion/           # tokens de movimento em TS (durações, easings, distâncias), espelho dos tokens CSS
├── strings/          # todos os textos da interface em pt-BR
├── stores/           # stores Pinia globais
├── styles/
│   ├── tokens/       # CSS custom properties: cores, espaço, raios, sombras, tipografia, movimento
│   ├── themes/       # valores dos tokens por tema do app: [data-theme="light|dark"]
│   ├── base/         # reset, tipografia, @font-face da Inter
│   ├── mixins/       # SÓ mixins e funções (não emite CSS; injetado em todo <style lang="scss">)
│   └── main.scss     # entrada global, importada em app/main.ts
└── types/            # tipos compartilhados (schema, rule types, DTOs)
```

Organização **por feature**: código que muda junto fica junto.

### Regras

- **Composition API + `<script setup lang="ts">`** em todos os componentes. Sem Options API em código novo.
- **TypeScript `strict`**. Proibido `any` (use `unknown` + narrowing). Tipos do schema e das regras são **uniões discriminadas** (`type` como discriminador).
- Componentes **pequenos e focados**; extraia lógica reutilizável para **composables** (`useFormBuilder`, `useFieldValidation`).
- Props tipadas com `defineProps<...>()`; eventos tipados com `defineEmits<...>()`. Fluxo de dados: **props para baixo, eventos para cima**.
- Nomes: componentes em `PascalCase.vue` (multi-palavra), composables `useXxx.ts`, stores `useXxxStore`.
- **Pinia**: usada para estado **de cliente** (estado do builder, seleção de campo, undo/redo, sessão do usuário). Stores no estilo *setup*.
- **TanStack Query**: usado para **estado de servidor** (formulários, respostas). Não duplicar dados de servidor dentro do Pinia. Defina `queryKey`s centralizadas e invalide-as após mutações.
- **Axios**: uma única instância configurada em `api/` (baseURL, `withCredentials: true`, interceptor de CSRF/401/erros). Componentes **não** chamam Axios diretamente; chamam funções de `api/` via TanStack Query.
- **Valibot**: schemas de validação do formulário público gerados a partir do schema JSON; mesmas regras e mesmos casos de teste do backend. Mensagens em português.
- **Renderer próprio**: um componente por tipo de campo, resolvido por um mapa `type → componente`. Adicionar um tipo de campo = criar componente + registrar no mapa + tipo no schema + regra de backend.
- **Uppy** apenas nos campos de arquivo: respeitar limites (tipos, tamanho, quantidade) configurados no schema; o backend revalida tudo.
- **vuedraggable** apenas no builder; a ordem dos campos vive no store do builder, e a persistência é feita ao salvar.
- Acessibilidade: `label` associado a cada input, navegação por teclado, mensagens de erro ligadas com `aria-describedby`, foco no primeiro erro ao enviar.
- Estilo: SCSS + CSS custom properties; sem Tailwind, sem libs de UI estilizadas. Use `@use`/`@forward` (nunca `@import`); mixins de `styles/mixins` (`up()`, `focus-ring`, `reduced-motion`, `visually-hidden`) já estão disponíveis nos componentes. BEM; sem `!important`; sem seletores profundos; todo valor visual via `var(--…)`.
- **Tema do app** (claro/escuro): `[data-theme="light|dark"]` no `<html>`; padrão segue `prefers-color-scheme`, escolha manual persistida, aplicada antes da primeira pintura (sem flash). O tema claro é projetado, não uma inversão automática do escuro. O **tema do formulário** (cores do dono) é independente e vale só no escopo do renderer, por custom properties.
- **Textos**: toda string visível em pt-BR vem de `strings/`; nome da marca numa única constante em `config/`.
- **Reka UI**: base para componentes interativos complexos (Dialog, Popover, Select, Combobox, Tabs, Tooltip, DropdownMenu) por cuidar de acessibilidade e foco. Nunca usar primitivas Reka direto nas features: envolva-as em um componente próprio `AppXxx.vue` em `components/` e estilize lá. Os campos do **renderer** continuam usando elementos HTML nativos, salvo quando o tipo de campo exigir uma primitiva (ex.: select pesquisável).
- **Iconify**: ícones apenas pelo componente `AppIcon`, da coleção **Lucide** empacotada no build (nunca buscada em runtime). Ícone sem `label` é decorativo (`aria-hidden`); botão só com ícone precisa de `label`.
- **Animação: divisão de responsabilidades**
  - Durações, easings e distâncias vêm sempre de `motion/` (TS) e dos tokens CSS correspondentes; nada de números soltos.
  - **Motion (`motion-v`)**: componentes, presença (`AnimatePresence`), layout e estados (hover/press). Primeira escolha.
  - **GSAP**: sequências coreografadas, contadores e gráficos (landing, resultados). Sempre via `useGsapContext` (escopo no elemento + `revert()` ao desmontar), em chunks separados. Nunca chamar `gsap` solto em componentes.
  - Nunca animar o mesmo elemento com as duas bibliotecas ao mesmo tempo.
  - Não aplicar animação de layout do Motion nos itens arrastáveis do `vuedraggable` (conflito com o SortableJS): o deslizar dos vizinhos usa a animação do próprio SortableJS, com os tokens de movimento.
  - Respeitar `prefers-reduced-motion` em **todas** as animações: `MotionConfig reduced-motion="user"`, `useGsapContext` não roda, e os tokens CSS de duração viram `0ms` (sem `!important`).
  - Animar só `transform`/`opacity` sempre que possível. O renderer público continua leve: animações discretas, que nunca atrasam o envio nem a exibição de erros.
- Lint e formatação: **ESLint** (config oficial do Vue + TypeScript) e **Prettier**. `vue-tsc --noEmit` deve passar sem erros.

### Testes (Vitest)

- Teste o **motor de validação** com `/fixtures/validation-cases.json` (mesmas fixtures do backend).
- Teste composables e stores de forma isolada (store do builder: adicionar, mover, duplicar, remover, undo/redo); componentes críticos (renderer de campos, upload, seletor de cor) com `@vue/test-utils`.
- Sem testes E2E por enquanto.

---

## 8. Contrato da API

- JSON em todas as rotas, prefixo `/api/v1`.
- Códigos HTTP corretos: `200/201/204`, `401`, `403`, `404`, `422` (validação), `429` (rate limit).
- Erros de validação no formato padrão do Laravel (`message` + `errors` por campo). O front mapeia `errors[field_id]` para o campo correspondente.
- Paginação em listagens (cursor ou página), nunca retornar listas ilimitadas.
- Rotas públicas (sem auth): `GET /forms/{slug}` (schema publicado) e `POST /forms/{slug}/submissions`.
- Upload de anexos: enviado junto com a resposta (`multipart/form-data`) ou em etapa própria de upload vinculada à resposta; a decisão deve ser documentada em `docs/` antes da implementação.
- Mudanças que quebram o contrato exigem nova versão da API.

---

## 9. Qualidade, Git e fluxo de trabalho

- **Commits** no padrão **Conventional Commits** (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`).
- Branches curtas por funcionalidade; PRs pequenos e focados.
- Todo PR deve: passar em Pint, Pest, ESLint, `vue-tsc` e Vitest; incluir testes; atualizar a documentação quando mudar comportamento.
- Nunca commitar segredos. `.env` fora do Git; manter `.env.example` atualizado.
- Não fazer refactors grandes misturados com features.
- Dependências novas exigem justificativa e aprovação (ver seção 2).

### Definição de pronto

Uma tarefa só está pronta quando:

1. Funciona no caminho feliz **e** nos caminhos de erro.
2. Tem testes (Pest e/ou Vitest) passando.
3. Validação existe no backend (e espelhada no front quando aplicável).
4. Lint, formatação e checagem de tipos passam.
5. Não introduz dependência ou tecnologia fora da stack.

---

## 10. Instruções específicas para agentes de IA

- **Leia antes de escrever**: examine o código existente e siga os padrões já adotados.
- **Não invente tecnologias**: use apenas o que está na seção 2.
- **Não pule o backend**: nunca implemente validação só no front.
- **Mantenha o escopo**: faça o que foi pedido, sem refactors paralelos nem arquivos extras.
- **Sem dados sensíveis em logs, exemplos ou fixtures** (CPFs reais, emails reais).
- **Alterou regra de validação?** Atualize o PHP e as fixtures compartilhadas juntos; o espelho TypeScript é feito no trabalho do front, contra as mesmas fixtures.
- **Dúvida de arquitetura ou requisito?** Pergunte antes de implementar, em vez de assumir.
- Ao terminar, resuma o que mudou, o que foi testado e o que ficou pendente.

---

## 11. Ordem de implementação planejada

1. Estrutura do monorepo (`/api`, `/web`, `/fixtures`) e tooling (Pint, Pest, ESLint, Prettier, Vitest).
2. **Motor de validação anti-lixo** (PHP + Valibot) com fixtures compartilhadas.
3. Schema do formulário + endpoint público de envio de resposta.
4. Email ao dono via fila.
5. Anexos (Uppy + MediaLibrary).
6. Builder visual (vuedraggable) e painel de respostas.
