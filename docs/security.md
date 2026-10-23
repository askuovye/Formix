# Revisão de segurança

Revisão da API (fase 9, 2026-10-24) contra o checklist do briefing e o OWASP Top 10 (2021). Legenda: ✅ atendido · ⚠️ atendido com ressalva · ❌ pendente (com barreira).

Refazer esta revisão a cada funcionalidade nova que toque autenticação, envio público, arquivos ou dados pessoais.

## Checklist do projeto

| Item | Status | Como / onde |
|---|---|---|
| Autorização em toda rota privada, sem confiar em IDs do cliente (IDOR) | ✅ | Policies (`FormPolicy`, `SubmissionPolicy`, `ExportPolicy`) via `can:` em todas as rotas `/me`; recurso de outra pessoa → `404`. Listagens filtram por subconsulta do dono. Rota pública resolve só por slug publicado; edição usa `scopeBindings` + token. Testado usuário A × B em todos os endpoints |
| Sem mass assignment | ✅ | `$fillable`/`#[Fillable]` explícitos; campos de sistema só por `forceFill` nas Actions; `Submission`/`Export` sem campos preenchíveis |
| Sem SQL cru sem binding | ✅ | Só Eloquent/query builder; varredura sem `DB::raw`/`whereRaw`. Testes com payloads de SQL injection nas respostas e no slug |
| Sem `unserialize` de entrada externa | ✅ | Nenhum `unserialize`; o único conteúdo desserializado é o recibo de idempotência, criptografado com a `APP_KEY` |
| Sanctum SPA (domínios, cookies, CSRF) e CORS restrito | ⚠️ | `statefulApi()`, cookie `httpOnly`, `secure` padrão em produção, `SameSite=lax`, CORS só para `CORS_ALLOWED_ORIGINS` com credenciais. **Ressalva:** `SESSION_DOMAIN` e `SANCTUM_STATEFUL_DOMAINS` precisam ser preenchidos no deploy |
| Rate limiting em auth, envio, downloads e exportações | ✅ | Login, cadastro, senha, verificação, envio público (por IP+formulário, IP e formulário), edição, downloads, exportações, health |
| Cabeçalhos de segurança | ✅ | `SetSecurityHeaders`: `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP, CSP, HSTS em HTTPS |
| Upload: MIME real, antivírus, disco privado, URLs assinadas, nomes gerados, limites | ⚠️ | Tudo exceto **antivírus** (indisponível na hospedagem; mitigado por lista fechada de tipos e download como `attachment` + `sandbox`). B4 |
| LGPD: coleta mínima, hash de IP, retenção, exclusão sob pedido | ⚠️ | HMAC de IP com sal, retenção por formulário, exclusão de respostas, prova de aceite dos termos. **Pendente:** autoexclusão de conta (B7) e expurgo de formulários excluídos (B16) |
| Nunca logar respostas, tokens ou arquivos | ✅ | Logs só com IDs e tipo do erro; `#[SensitiveParameter]` em senhas/tokens; **erros de banco registrados sem os valores das consultas** (`ReportQueryExceptionWithoutBindings`, testado) |
| Horizon e rotas administrativas protegidos | ✅ | Não há Horizon nem rotas administrativas (D14). O disco `local` não serve arquivos automaticamente |
| Dependências sem vulnerabilidades conhecidas | ✅ | `composer audit` limpo (roda em `composer check`) |

## OWASP Top 10 (2021)

| Risco | Status | Notas |
|---|---|---|
| A01 Quebra de controle de acesso | ✅ | Ver IDOR acima. Links de verificação, download e edição são assinados ou exigem token; não há rota que liste dados de outro dono |
| A02 Falhas criptográficas | ⚠️ | bcrypt nas senhas; SHA-256 para tokens aleatórios (edição, idempotência); HMAC-SHA256 com sal para IP; recibos e Jobs com token **criptografados**. **Ressalva:** HTTPS depende do deploy (HSTS só é enviado sob HTTPS) |
| A03 Injeção | ✅ | SQL por parâmetros; HTML dos emails escapado e **sem Markdown** (D28); CSV com fórmulas neutralizadas (D31); assunto de email sem quebras de linha; nenhuma regex vinda do schema (ReDoS) |
| A04 Design inseguro | ⚠️ | Anti-enumeração de contas, honeypot, idempotência, limites de payload e de schema, cópia ao respondente limitada por destinatário. **Pendentes de abuso:** denúncia e suspensão (B1, B2), confirmar emails de notificação (B13), CAPTCHA (B12) |
| A05 Configuração incorreta | ⚠️ | Erros JSON sem detalhes fora do debug; mensagens do framework substituídas; `expose_php=Off`; CSP. **Ressalva:** `APP_DEBUG=false` e `APP_ENV=production` obrigatórios no deploy (ver `api/README.md`) |
| A06 Componentes vulneráveis | ✅ | `composer audit` limpo; o front deve rodar `npm audit` no próprio CI |
| A07 Falhas de identificação e autenticação | ✅ | Política de senha (8–72, letras e números, checagem de vazamento em produção), rate limit por email e IP, mensagens e tempo iguais para email inexistente, `Session::regenerate` no login, invalidação no logout, troca de senha derruba outras sessões |
| A08 Falhas de integridade | ✅ | URLs assinadas com expiração; versões publicadas e revisões imutáveis no model; payloads de fila com token criptografados |
| A09 Falhas de log e monitoramento | ⚠️ | Falhas definitivas de Jobs registradas sem dados pessoais; health check com atraso da fila. **Pendente:** alertas (ex.: muitos 429 ou falhas de login) e monitoramento externo (B17) |
| A10 SSRF | ✅ | A API não busca URLs informadas por usuários. A única chamada externa é a checagem de senha vazada (host fixo, k-anonymity) |

## Verificado nesta revisão

- Varredura por `unserialize`, `eval`, `exec`/`system`/`shell_exec`, `DB::raw`/`whereRaw`, `$guarded = []`, `env()` fora de `config/`: **nada encontrado**.
- Todos os `Log::` da aplicação: só IDs e classe do erro.
- **Corrigido nesta fase:** a `QueryException` padrão do Laravel gravaria no log o SQL com os valores (respostas, emails, hash de senha). Agora o log leva só o SQL com placeholders.
