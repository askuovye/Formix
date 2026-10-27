# Banco de dados

Esquema atual (MariaDB/MySQL) e histórico de alterações. Atualizar a cada migration nova.

## Tabelas atuais

| Tabela | Origem | Uso |
|---|---|---|
| `users` | Laravel | donos dos formulários. PK `id` **ULID**; `email` único (sempre minúsculo) |
| `password_reset_tokens` | Laravel | recuperação de senha (token com hash, expira em 60 min) |
| `sessions` | Laravel | sessões do Sanctum SPA (`SESSION_DRIVER=database`); `user_id` ULID |
| `cache`, `cache_locks` | Laravel | cache, rate limiting e locks (`withoutOverlapping`, idempotência) |
| `jobs`, `failed_jobs`, `job_batches` | Laravel | fila (`QUEUE_CONNECTION=database`) |
| `legal_acceptances` | app | prova do aceite (LGPD): `user_id`, `document` (`terms`/`privacy`), `version`, `accepted_at`, `ip_hash` (HMAC). Uma linha por documento e versão; apagada junto com o usuário |
| `forms` | app | `id` ULID, `user_id`, `title`, `description`, `slug` (único, inclusive excluídos), `status` (`draft`/`published`/`closed`), `schema` (json, **rascunho**), `schema_version` (última versão publicada; 0 = nunca), `has_unpublished_changes`, `settings` (json, não versionado), `submissions_count` (mantido na transação de envio), `published_at`, `closed_at`, timestamps, `deleted_at`. Índices: `slug` único, `(user_id, status)`, FK `user_id` |
| `form_versions` | app | snapshot **imutável** por publicação: `form_id`, `version`, `title`, `description`, `schema`, `published_at`. Único `(form_id, version)` |
| `submissions` | app | `id` ULID, `form_id`, `schema_version`, `data` (json, respostas normalizadas por id de campo), `search_text` (texto das respostas em minúsculas e sem acentos, para a busca do painel; mantido pelo model), `respondent_email` (email do primeiro campo de email, em minúsculas; "respostas desta pessoa"), `ip_hash` (HMAC), `user_agent` (≤ 255), `idempotency_key` (SHA-256), `edit_token_hash` (SHA-256), `editable_until`, `edited_at`, `owner_notified_at`, `respondent_notified_at` (idempotência dos emails), timestamps. Únicos/índices: `(form_id, idempotency_key)`, `(form_id, created_at)`, `respondent_email`, FK `form_id` |
| `submission_revisions` | app | conteúdo anterior de uma resposta editada (imutável): `submission_id`, `data`, `files` (metadados dos anexos substituídos), `replaced_at` |
| `form_assets` | app | imagens de capa e logo do tema (públicas, **imutáveis**): `form_id`, `kind` (`cover`/`logo`), `disk`, `path`, `mime_type`, `size`, `created_at`. Referenciadas pelo id em `schema.theme` do rascunho e das versões |
| `exports` | app | exportações CSV: `user_id`, `form_id`, `status` (`pending`/`processing`/`ready`/`failed`), `format` (`csv`/`pdf`), `from`/`to` (período), `search` (busca usada ao pedir), `respondent_email` (filtro de pessoa usado ao pedir), `disk`, `path`, `rows_count`, `completed_at`, `expires_at` (arquivo apagado ao vencer). Índices `(user_id, status)` e `expires_at` |
| `media` | Spatie MediaLibrary | anexos das respostas: `model_type`/`model_id` (ULID) = `Submission`, `collection_name` = `field_<id do campo>`, `uuid` (usado no link de download), `file_name` (UUID + extensão real), `name` (nome original limpo), `custom_properties.field_id` e `detected_mime_type` |

## Planejadas (próximas fases)

Nenhuma no momento.

**Atenção:** as FKs com `cascadeOnDelete` apagam linhas de `media` sem disparar eventos de model, e os arquivos ficariam órfãos no disco. Exclusões de respostas e formulários (painel, retenção) devem passar pelos models (`$submission->delete()`), que apagam os arquivos.

## Histórico

| Data | Alteração | Motivo |
|---|---|---|
| 2026-10-23 | Removida a migration `personal_access_tokens` (Sanctum) | autenticação só em modo SPA (D6) |
| 2026-10-23 | Banco PostgreSQL → MariaDB/MySQL; `sessions`, `cache` e `jobs` passam a ser usadas | hospedagem compartilhada (D14, D16) |
| 2026-10-23 | `users.id` → ULID; `sessions.user_id` → ULID; `media` com `ulidMorphs` | IDs públicos não sequenciais (D17). Migrations ainda não versionadas, por isso editadas no lugar |
| 2026-10-23 | Nova tabela `legal_acceptances` | aceite de Termos e Política no cadastro (D20) |
| 2026-10-23 | Novas tabelas `forms` e `form_versions` | builder e versionamento (D21). Desvios do AGENTS.md §4: `title`/`description` também no snapshot (fazem parte do conteúdo publicado); colunas `has_unpublished_changes`, `published_at`, `closed_at`; a versão não fica dentro do JSON do schema, só em `schema_version` |
| 2026-10-24 | Novas tabelas `submissions` e `submission_revisions`; `forms.submissions_count` | envio de respostas e edição pelo respondente (D25–D27). Desvios do AGENTS.md §4: colunas de idempotência, edição e `updated_at` |
| 2026-10-24 | `submissions.owner_notified_at` e `respondent_notified_at` | idempotência dos emails (D28). Migration ainda não versionada, editada no lugar |
| 2026-10-24 | `submission_revisions.files`; uso da tabela `media` | anexos (D30). Migration ainda não versionada, editada no lugar |
| 2026-10-24 | Nova tabela `exports` | exportação CSV em fila (D31) |
| 2026-10-25 | Nova tabela `form_assets`; o JSON de `forms.schema`/`form_versions.schema` ganha `kind`, blocos, `rating`/`scale`, `visibility` e `theme` | contrato do front (D32, D33). Sem migração de dados: itens sem `kind` são campos |
| 2026-10-26 | `submissions.search_text` (com preenchimento das linhas existentes) | busca nas respostas do painel: o json de `data` escapa acentos, então o LIKE roda nesta cópia normalizada |
| 2026-10-27 | `exports.search` e `exports.format` | a exportação respeita a busca do painel e pode sair em PDF (D35) |
| 2026-10-27 | `submissions.respondent_email` (com índice e preenchimento das existentes) e `exports.respondent_email` | filtro "todas as respostas desta pessoa" sem consultar dentro do JSON de `data` (AGENTS.md §6) |
