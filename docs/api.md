# Contrato da API (v1)

Base: `{APP_URL}/api/v1`. Todas as respostas são JSON. Atualizado a cada fase.

## Convenções

- **Autenticação**: Sanctum SPA. Antes do primeiro `POST`, a SPA chama `GET {APP_URL}/sanctum/csrf-cookie`; o Axios devolve o cookie `XSRF-TOKEN` no cabeçalho `X-XSRF-TOKEN` (`withCredentials: true`).
- **Sucesso**: recursos em `{ "data": … }`; respostas só com mensagem em `{ "message": "…" }`.
- **Erro**: `{ "message": "…" }`. Validação (`422`): `{ "message": "…", "errors": { "campo": ["…"] } }`.
- **Códigos**: `401` sem sessão · `403` sem permissão · `404` não encontrado · `419` CSRF/sessão expirada (recarregar o cookie CSRF) · `422` validação · `429` limite de tentativas (ver `Retry-After`).

### Objeto `User`

```json
{ "id": "01JB…(ULID)", "name": "Maria Souza", "email": "maria@example.com", "email_verified": true, "created_at": "2026-10-23T12:00:00+00:00" }
```

## Autenticação (`/auth`)

| Método e rota | Auth | Corpo | Sucesso | Erros |
|---|---|---|---|---|
| `POST /auth/register` | — | `name`, `email`, `password`, `password_confirmation`, `terms_accepted` (`true`) | `202` mensagem genérica | `422`, `429` (3/h por email, 10/h por IP) |
| `POST /auth/login` | — | `email`, `password`, `remember?` | `200` `{ data: User }` | `422` em `errors.email` (genérico), `429` (5/min por email+IP) |
| `POST /auth/logout` | sim | — | `204` | `401` |
| `GET /auth/me` | sim | — | `200` `{ data: User }` | `401` |
| `POST /auth/forgot-password` | — | `email` | `202` mensagem genérica | `422`, `429` |
| `POST /auth/reset-password` | — | `token`, `email`, `password`, `password_confirmation` | `200` mensagem | `422` em `errors.token` ou `errors.password` |
| `GET /auth/email/verify/{id}/{hash}?expires&signature` | — | — | `200` mensagem | `422` em `errors.signature` (inválido/expirado) |
| `POST /auth/email/verification-notification` | sim | — | `202` mensagem | `401`, `429` |

**Senha**: 8 a 72 caracteres, com letras e números.

**Cadastro**: a resposta é a mesma se o email já existir (decisão D18). Mostrar "verifique seu email"; o usuário não fica logado.

**Termos**: o cadastro exige um checkbox, desmarcado por padrão, "Li e aceito os [Termos de Uso] e a [Política de Privacidade]", com links para os textos, enviado como `terms_accepted: true`. Sem ele: `422` em `errors.terms_accepted`.

## Documentos legais (público)

| Método e rota | Resposta |
|---|---|
| `GET /legal/terms` | `{ data: { document, title, version, updated_at, content_markdown, content_html } }` |
| `GET /legal/privacy` | idem |

O texto vem em Markdown simples (títulos `#`/`##`, parágrafos, listas, negrito e uma tabela na política) e também já convertido em `content_html`, que a SPA exibe direto (D34). Documento inexistente: `404`.

## Rotas que a SPA precisa ter (links enviados por email)

| Rota da SPA | Parâmetros | O que fazer |
|---|---|---|
| `/verificar-email` | `id`, `hash`, `expires`, `signature` | `GET /auth/email/verify/{id}/{hash}?expires=…&signature=…` |
| `/redefinir-senha` | `token`, `email` | formulário de nova senha → `POST /auth/reset-password` |
| `/entrar` | — | tela de login |
| `/esqueci-a-senha` | — | formulário → `POST /auth/forgot-password` |
| `/f/{slug}/respostas/{id}/editar` | token em `#token=…` | edição pelo respondente (ver "Página pública") |
| `/painel/formularios/{formId}/respostas/{submissionId}` | — | detalhe da resposta no painel (link do email ao dono) |
| `/painel/formularios/{formId}/configuracoes` | — | configurações do formulário (link "alterar notificações") |
| `/privacidade` | — | Política de Privacidade (`GET /legal/privacy`) |

## Formulários do dono (`/me/forms`)

Todas exigem login. Formulário de outra pessoa → `404`.

| Método e rota | Corpo | Sucesso | Erros |
|---|---|---|---|
| `GET /me/forms?status=&q=&per_page=&cursor=` | `q?` busca no título (≤ 100) | `200` lista de `FormSummary` + `meta.next_cursor`/`prev_cursor` (paginação por cursor, `per_page` ≤ 50, padrão 20) | `422` |
| `POST /me/forms` | `title`, `description?` | `201` `{ data: Form }` (rascunho, schema vazio) | `422` |
| `GET /me/forms/{id}` | — | `200` `{ data: Form }` | `404` |
| `PATCH /me/forms/{id}` | qualquer subconjunto de `title`, `description`, `slug`, `schema`, `settings` | `200` `{ data: Form }` | `422` |
| `DELETE /me/forms/{id}` | — | `204` (exclusão lógica; o link público para de funcionar) | `404` |
| `POST /me/forms/{id}/publish` | — | `200` `{ data: Form }`. Também **reabre** formulário fechado | `403` email não confirmado, `422` sem campos ou configuração incoerente |
| `POST /me/forms/{id}/close` | — | `200` `{ data: Form }` | `409` se não estiver publicado |

- **`schema`** é enviado inteiro (substitui o rascunho). **`settings`** é parcial: só as chaves enviadas mudam.
- **`slug`** só pode mudar antes da primeira publicação (depois: `422` em `errors.slug`).
- Editar título, descrição ou schema de um formulário publicado **não muda o que o público vê** até publicar de novo (`has_unpublished_changes: true`). Configurações valem na hora.

### Objeto `Form`

```json
{
  "id": "01JB…", "title": "Inscrição", "slug": "inscricao-a1b2c3", "status": "draft|published|closed",
  "published_version": 0, "has_unpublished_changes": true,
  "public_url": "https://app.exemplo.com/f/inscricao-a1b2c3",
  "published_at": null, "closed_at": null, "created_at": "…", "updated_at": "…",
  "description": null,
  "schema": { "fields": [] },
  "settings": {
    "notification_emails": [], "success_message": null, "max_submissions": null,
    "opens_at": null, "closes_at": null,
    "send_copy_to_respondent": false, "respondent_email_field": null,
    "allow_respondent_edit": false, "retention_days": null
  }
}
```

`FormSummary` é o mesmo objeto sem `description`, `schema` e `settings`, e com `submissions_count` e `last_submission_at` (ISO 8601 ou `null`). Datas em ISO 8601 (UTC). `notification_emails` vazio = email do dono.

### Formato do `schema`

```json
{
  "fields": [
    {
      "id": "nome", "type": "short_text", "label": "Nome completo",
      "help_text": null, "required": true, "placeholder": null,
      "strictness": "medio",
      "rules": [{ "type": "min_length", "value": 5, "message": "Mensagem própria (opcional)" }]
    },
    { "id": "nota", "type": "single_choice", "label": "Nota", "display": "radio",
      "options": [{ "value": "boa", "label": "Boa" }] },
    { "id": "doc", "type": "file", "label": "Comprovante",
      "file": { "accept": ["pdf", "image"], "max_files": 1, "max_size_mb": 5 } }
  ]
}
```

| Chave | Regras |
|---|---|
| `id` | `^[a-z][a-z0-9_]{0,39}$`, único no formulário |
| `type` | `short_text`, `long_text`, `email`, `number`, `date`, `single_choice`, `multiple_choice`, `file` |
| `label` | obrigatório, até 200 · `help_text` até 500 · `placeholder` até 120 (texto, email, número) |
| `options` | só em escolha; 1 a 100; `value` `^[a-z0-9][a-z0-9_-]{0,59}$` único · `display` (`radio`/`select`) só em `single_choice` |
| `file` | só em `file`; `accept` ⊂ `pdf`, `image`, `document`, `spreadsheet`, `text`; `max_files` 1–10 (padrão 1); `max_size_mb` 1–10 (padrão 5) |
| `strictness` | `leve`, `medio`, `estrito` (só texto). **Não volta na resposta**: é trocado pelas regras correspondentes |
| `rules` | até 20 por campo, sem repetir `type`; `message` opcional até 200 |

Regras por tipo de campo:

| Regra | Parâmetros | Campos |
|---|---|---|
| `min_length`, `max_length` | `value` 1–10000 | texto curto e longo |
| `min_distinct_chars` | `value` 1–50 | texto |
| `no_repeated_chars` | `threshold` 0.5–1 | texto |
| `no_sequences` | `value?` 3–20 | texto |
| `no_only_symbols` | — | texto |
| `blocklist` | `values`: até 200 textos de até 100 | texto |
| `min_words` | `value` 1–500 | texto longo |
| `cpf`, `cnpj`, `phone_br`, `cep` | — | texto curto |

### Blocos, estrelas, escala, lógica condicional e tema

Formato completo em [`frontend-contract-requests.md`](frontend-contract-requests.md). Resumo:

- Cada item tem `kind`: `input` (campo, tudo acima) ou `block` (`section_title`, `text`, `divider`, `page_break`, com `text` quando aplicável). Etapas = trechos entre `page_break`.
- Tipos `rating` (`rating.max` 3–10) e `scale` (`scale.min`/`max` entre 0 e 10, rótulos opcionais); resposta inteira.
- `visibility: { action: "show"|"hide", when: { field, operator, value? } }` em campos; a origem precisa vir antes. O servidor ignora e descarta campos ocultos no envio.
- `schema.theme` (versionado): preset, cores, raio, fonte, estilo do botão, alinhamento, `cover`/`logo` com `asset_id` e `url`.

| Método e rota | Corpo | Sucesso | Erros |
|---|---|---|---|
| `POST /me/forms/{id}/assets` | multipart `file` (JPG/PNG/WebP ≤ 5 MB), `kind` (`cover`/`logo`) | `201` `{ data: { id, kind, url, mime_type, size } }` | `404`, `409` (20 por formulário), `422`, `429` (30/h) |
| `DELETE /me/forms/{id}/assets/{assetId}` | — | `204` | `404`, `409` (em uso pelo rascunho ou por versão publicada) |
| `GET /assets/{assetId}` (público) | — | a imagem, cache imutável | `404` |

O servidor devolve o schema **normalizado**: chaves desconhecidas são descartadas e os padrões preenchidos (`display: "radio"`, `max_files: 1`, `max_size_mb: 5`). Erros vêm por caminho, ex.: `errors["schema.fields.2.options.0.value"]`. Schema acima de 256 KB: `422` em `errors.schema`.

## Painel de respostas (`/me`)

Todas exigem login. Resposta ou exportação de outra pessoa → `404`.

| Método e rota | Corpo / query | Sucesso | Erros |
|---|---|---|---|
| `GET /me/submissions` | `form?` (id de formulário seu), `q?` (busca nas respostas, sem diferenciar maiúsculas nem acentos, ≤ 100), `email?` (respostas de uma pessoa: email do primeiro campo de email, exato, sem diferenciar maiúsculas), `from?`, `to?` (`AAAA-MM-DD` = dia inteiro em Brasília, ou ISO 8601), `sort?` (`newest`/`oldest`), `per_page?` (≤ 50), `cursor?` | `200` lista de `SubmissionSummary` + `meta.next_cursor` | `422` |
| `GET /me/submissions/{id}` | — | `200` `{ data: Submission }` | `404` |
| `GET /me/submissions/{id}/pdf` | — | `200` PDF da resposta (`attachment`, `nosniff`, sem cache) | `404`, `429` (20/min) |
| `DELETE /me/submissions/{id}` | — | `204` (definitivo, apaga anexos) | `404` |
| `POST /me/forms/{formId}/exports` | `from?`, `to?`, `q?` (mesma busca da lista, ≤ 100), `email?`, `format?` (`csv` padrão, ou `pdf`: até 300 respostas, uma por página) | `202` `{ data: Export }` | `404`, `409` (3 em andamento), `422` (inclusive PDF acima do teto, em `format`), `429` (10/h) |
| `GET /me/exports/{id}` | — | `200` `{ data: Export }` | `404` |
| `GET /me/forms/{formId}/results` | `from?`, `to?`, `q?`, `email?` (mesmos filtros da lista) | `200` `{ data: FormResults }` | `404`, `422` |

### `SubmissionSummary`

```json
{ "id": "01JB…", "form": { "id": "01JB…", "title": "Inscrição" }, "schema_version": 1,
  "preview": [{ "label": "Nome completo", "value": "Maria Souza" }],
  "files_count": 1, "created_at": "…", "edited_at": null }
```

### `Submission`

```json
{
  "id": "01JB…", "form": { "id": "…", "title": "…", "slug": "…" }, "schema_version": 1,
  "respondent_email": "maria@example.com",
  "answers": [{ "field_id": "nome", "label": "Nome completo", "value": "Maria Souza", "multiline": false, "answered": true }],
  "files": [{ "field_id": "comprovante", "label": "Comprovante",
              "files": [{ "name": "Recibo.pdf", "size": "120 KB", "url": "https://api…/files/…?signature=…" }] }],
  "revisions": [{ "replaced_at": "…", "answers": [ … ], "replaced_files": [{ "field_id": "…", "name": "…", "size": 1234, "mime_type": "…" }] }],
  "created_at": "…", "edited_at": "…", "editable_until": "…"
}
```

`answers` vem na ordem e com os rótulos da versão de envio; não respondido = `answered: false` e `value: "(não respondido)"`. Os links de `files` valem 7 dias.

### `FormResults`

```json
{
  "total": 42, "last_7_days": 9, "edited": 3,
  "average_duration_seconds": 95,
  "devices": [{ "device": "desktop", "count": 20 }, { "device": "mobile", "count": 19 }, { "device": "tablet", "count": 3 }],
  "previous": { "total": 30, "average_duration_seconds": 110 },
  "timeline": { "unit": "day|week", "points": [{ "date": "2026-10-01", "count": 4 }] },
  "fields": [
    { "id": "nome", "label": "Nome", "type": "short_text", "answered": 40,
      "latest": [{ "submission_id": "01JB…", "value": "Maria Souza", "created_at": "…" }] },
    { "id": "nota", "label": "Nota", "type": "single_choice", "answered": 42,
      "options": [{ "value": "boa", "label": "Boa", "count": 30 }] },
    { "id": "estrelas", "type": "rating", "answered": 41, "average": 4.2,
      "distribution": [{ "value": 1, "count": 0 }, { "value": 5, "count": 20 }] },
    { "id": "idade", "type": "number", "answered": 39, "min": 18, "max": 70, "average": 34.5 },
    { "id": "quando", "type": "date", "answered": 12, "earliest": "2026-01-10", "latest": "2026-03-02" },
    { "id": "rg", "type": "file", "answered": 15 }
  ]
}
```

- Campos da versão publicada mais recente, na ordem do formulário (sem blocos). Rascunho nunca publicado: `fields: []`.
- `answered`: respostas com valor no campo (oculto pela lógica ou em branco não conta). Arquivo: respostas com pelo menos um anexo.
- Escolhas: opções na ordem do formulário, inclusive as com zero; valores que só existiam em versões antigas vêm no fim, com o próprio valor como rótulo. Múltipla escolha conta cada opção marcada.
- Estrelas/escala: `distribution` cobre todos os valores possíveis.
- Texto, texto longo e email: as 5 respostas mais recentes (até 200 caracteres), da mais nova para a mais antiga.
- `average_duration_seconds`: média das respostas com tempo medido (`null` se nenhuma). Informativo: vem do navegador.
- `devices`: sempre os três tipos, nessa ordem; respostas sem User-Agent não entram. iPad com iPadOS conta como desktop (se apresenta como Mac).
- `previous`: só com `from`; total e tempo médio do período anterior de mesmo tamanho (com a mesma busca e pessoa), para a variação. Sem `from`: `null`.
- `timeline` no fuso de Brasília: últimos 30 dias, ou o período filtrado; acima de 90 dias, por semana (`date` = segunda-feira).

### `Export`

```json
{ "id": "01JB…", "form_id": "…", "status": "pending|processing|ready|failed", "format": "csv|pdf", "from": null, "to": null, "search": null, "email": null,
  "rows_count": 120, "download_url": "https://api…/exports/…/download?signature=…",
  "created_at": "…", "completed_at": "…", "expires_at": "…" }
```

Consultar `GET /me/exports/{id}` até `status = ready` (a fila roda a cada minuto). `download_url` só vem quando pronta, vale 60 minutos e é regerada a cada consulta; o arquivo some em `expires_at` (24 h). CSV com `;` e UTF-8 (abre direto no Excel em português). PDF com uma resposta por página; anexos aparecem só pelo nome e tamanho.

## Página pública do formulário (sem login)

Rascunho nunca publicado, formulário excluído e slug inexistente respondem **404**, todos iguais.

| Método e rota | Corpo / cabeçalhos | Sucesso | Erros |
|---|---|---|---|
| `GET /forms/{slug}` | — | `200` `{ data: PublicForm }` | `404`, `429` (60/min por IP) |
| `POST /forms/{slug}/submissions` | `{ "answers": { "<id do campo>": valor }, "website": "", "duration_seconds"?: 95 }` · `Idempotency-Key?` | `201` `{ data: Receipt }` | `409` não aceita respostas · `413` corpo > 1 MB · `422` · `429` |
| `GET /forms/{slug}/submissions/{id}` | `X-Edit-Token` | `200` `{ data: EditableSubmission }` | `404` token errado · `409` edição bloqueada · `410` prazo de 4 h passou |
| `PUT /forms/{slug}/submissions/{id}` | `X-Edit-Token` · `{ "answers": { … } }` | `200` `{ data: EditableSubmission }` | idem + `422` |

### `PublicForm`

```json
{
  "slug": "inscricao-a1b2c3", "version": 1, "title": "Inscrição", "description": null,
  "accepts_submissions": true,
  "availability": "open|closed|not_open_yet|ended|limit_reached",
  "unavailable_message": null,
  "schema": { "fields": [ … ] },
  "opens_at": null, "closes_at": null,
  "allows_respondent_edit": false,
  "privacy_policy_url": "https://app.exemplo.com/privacidade"
}
```

Quando `accepts_submissions` é `false`, `schema` vem `null`; mostrar `unavailable_message`. **Exibir o link `privacy_policy_url`** perto do botão de enviar (barreira B6).

### Envio

- **Valores por tipo:** texto e email = string; `number` = número ou string (aceita vírgula); `date` = `"AAAA-MM-DD"`; `single_choice` = `value` da opção; `multiple_choice` = lista de `value`. Campos não respondidos podem ser omitidos ou `null`.
- **Com anexos:** `multipart/form-data` com
  - `answers` = **texto JSON** das respostas (`JSON.stringify(answers)`);
  - `files[<id do campo>][]` = cada arquivo (Uppy: `fieldName: 'files[comprovante][]'`);
  - `website` = honeypot vazio;
  - `duration_seconds` (opcional).

  O tipo é conferido **pelo conteúdo** do arquivo. Limites por campo vêm do schema (`file.accept`, `max_files`, `max_size_mb`); a soma por envio é 25 MB (padrão da instância). Erros: `errors["<id do campo>"]` (um por campo) e `errors.files` (soma total).
- **Edição com anexos:** `POST /forms/{slug}/submissions/{id}` com `_method=PUT` no corpo multipart (o PHP só lê multipart em POST). Enviar arquivos para um campo **substitui** os anteriores daquele campo; não enviar **mantém** os atuais.
- **`duration_seconds`** (opcional): segundos entre abrir a página e enviar, medidos no navegador. Só alimenta o tempo médio dos resultados: inteiro de 1 a 7200; fora disso o valor é descartado, sem erro. Edições não mandam.
- **Erros de validação** vêm por id de campo: `errors["nome"]`, `errors["email"]`, um por campo, já com o rótulo na mensagem. Também podem vir `errors.answers`, `errors.idempotency_key` e `errors.captcha`.
- **Honeypot:** a página deve ter um input `website` invisível (fora da tela, `tabindex="-1"`, `autocomplete="off"`, `aria-hidden="true"`), sempre enviado vazio.
- **`Idempotency-Key`:** gere um UUID quando a página do formulário abrir e reutilize-o em retentativas do mesmo envio. Reenviar a mesma chave devolve o mesmo recibo, sem duplicar.

### `Receipt`

```json
{ "id": "01JB…", "success_message": "Resposta enviada. Obrigado!", "edit_url": null, "editable_until": null }
```

Com a edição ativa, `edit_url` é `{FRONTEND_URL}/f/{slug}/respostas/{id}/editar#token=…` e `editable_until` é o envio + 4 h.

### Edição pelo respondente

A rota da SPA `/f/{slug}/respostas/{id}/editar` lê o token do **fragmento** (`location.hash`, nunca da query) e o envia no cabeçalho `X-Edit-Token`. `EditableSubmission`:

```json
{ "id": "01JB…", "version": 1, "title": "…", "description": null, "schema": { "fields": [ … ] },
  "answers": { "nome": "Maria Souza" },
  "files": [ { "field_id": "comprovante", "files": [ { "name": "recibo.pdf", "size": 196608 } ] } ],
  "editable_until": "…", "edited_at": null }
```

O `schema` é o da versão com que a resposta foi enviada (pode ser diferente da versão atual). `files` lista os anexos atuais por campo, na ordem do schema, só com nome e tamanho em bytes (sem link de download); campos sem arquivo ficam de fora.

## Outros

| Método e rota | Descrição |
|---|---|
| `GET /exports/{id}/download?expires&signature` | download do arquivo (CSV ou PDF) por link assinado (de `Export.download_url`). Vencido/expirado → `403`/`410` |
| `GET /files/{uuid}?expires&signature` | download de anexo por **link assinado** (gerado pela API: emails ao dono e, na fase 8, o painel). Sempre `Content-Disposition: attachment`. Link adulterado ou vencido → `403`; anexo de resposta/formulário excluído → `404` |
| `GET /health` | `{ data: { status: ok|degraded|down, checks: {…} } }`; `503` quando `down` |
