# Decisões de arquitetura

Registro curto de decisões e seus motivos. Formato: **decisão**, *por quê*, consequências.
Decisões novas entram no fim; decisões revogadas são marcadas, não apagadas.

---

## D1. Ambiente local em Docker, espelhando a hospedagem

- **Decisão:** `api/compose.yaml` sobe app, scheduler (simula o cron), MariaDB 11.4 e Mailpit. Comandos (`artisan`, `pest`) rodam no container `app`. A imagem tem só as extensões disponíveis na hospedagem compartilhada (sem `redis`/`pcntl`).
- *Por quê:* desenvolver no mesmo banco e nas mesmas limitações da produção evita surpresas no deploy.
- A imagem Docker é **só de desenvolvimento**; produção é a hospedagem compartilhada (D14).

## D2. Somente API JSON

- **Decisão:** toda resposta é JSON (`shouldRenderJsonWhen` sempre verdadeiro). Removidos do esqueleto: rota `/` de boas-vindas, Vite, `package.json`, `resources/js|css`.
- *Por quê:* AGENTS.md §3: o back nunca renderiza HTML de aplicação (exceto emails).

## D3. Formato de erro

- **Decisão:** `ApiExceptionRenderer` responde `{ "message": "..." }` em português. Status genéricos (401, 403, 404, 405, 419, 413, 429, 500, 503) **sempre** usam mensagem fixa; outros (ex.: 409, 410 de regras de domínio) mantêm a mensagem lançada pelo código. Validação (422) mantém o formato padrão do Laravel (`message` + `errors`).
- *Por quê:* as mensagens do framework vazam detalhes (o 404 de model binding expõe o nome da classe do model). Fora do debug, erros 500 nunca mostram a mensagem original.

## D4. Health check com criticidade

- **Decisão:** `GET /api/v1/health` verifica o banco (**crítico** → 503) e o atraso da fila (**não crítico** → 200 com `degraded` se houver job pendente há mais de 10 minutos, sinal de que o cron parou). `/up` do framework continua existindo.
- *Por quê:* com a fila parada a API ainda recebe respostas; derrubá-la pelo health check pioraria o incidente.

## D5. Filas e conexões

- **Decisão:** filas `emails` e `default` na conexão `database` (`retry_after` 210s) e `exports` na `database-long` (660s), na mesma tabela `jobs`. Ambas com `after_commit = true`.
- *Por quê:* um `retry_after` alto único faria um job curto travado esperar 11 minutos para nova tentativa. `after_commit` garante que nenhum job processe dados de uma transação desfeita.

## D6. Sanctum somente em modo SPA

- **Decisão:** autenticação por sessão (cookie httpOnly + CSRF). A migration de `personal_access_tokens` foi removida.
- *Por quê:* não há cliente que use tokens de API; menos superfície e menos uma tabela.

## D7. Proxies confiáveis explícitos

- **Decisão:** `TRUSTED_PROXIES` (lista vazia por padrão).
- *Por quê:* rate limit e hash de IP dependem do IP real. Confiar em qualquer `X-Forwarded-For` permitiria forjar o IP e burlar o rate limit.

## D8. ~~Antivírus ClamAV assíncrono~~ (REVOGADA por D14)

- Desenho original: varredura por `clamd` (INSTREAM) em job, fail-closed, email só após varredura.
- **Revogada:** a hospedagem compartilhada não roda o daemon `clamd`. Antivírus fica fora do escopo até migrar para VPS; o desenho acima é o ponto de partida quando voltar.
- **Mitigação enquanto isso** (AGENTS.md §6): allowlist estrita de MIME real, nada executável/HTML/SVG, download só como `attachment` + `nosniff`, nenhum processamento do conteúdo.

## D9. Armazenamento de anexos no próprio servidor

- **Decisão:** disco local **privado** (`storage/app/private`), fora do `public_html`. O `league/flysystem-aws-s3-v3` permite migrar para S3-compatível só trocando `MEDIA_DISK`.
- *Por quê:* escolha do responsável pelo projeto.
- **Consequências:** backup do diretório de anexos é obrigatório; o espaço em disco do plano limita o volume de anexos.

## D10. Fixtures de validação são o contrato entre back e front

- **Decisão:** `fixtures/validation-cases.json` tem a chave `rules` no topo; o PHP é implementado no back e o espelho TypeScript no front, contra os mesmos casos.

## D11. Laravel Boost não adotado

- **Decisão:** removidos `api/CLAUDE.md` e `api/AGENTS.md` gerados pelo esqueleto do Laravel 13.
- *Por quê:* continham apenas o roteiro genérico de instalação do Laravel Boost (sem informação do projeto). O Boost é dependência fora da stack, e um AGENTS.md dentro de `api/` competiria com o da raiz.

## D12. `#[Fillable]` como forma de `$fillable` explícito

- **Decisão:** usar os atributos `#[Fillable]`/`#[Hidden]` do Laravel 13 em vez das propriedades `$fillable`/`$hidden`.
- *Por quê:* mesmo efeito (lista explícita, nunca `$guarded = []`) e é a convenção atual do framework.

## D13. Reabertura de formulário fechado

- **Decisão:** só o **dono** reabre um formulário `closed` (`closed` → `published`), verificado pela `FormPolicy`. Reabrir não gera versão nova se o schema não mudou.

## D14. Hospedagem compartilhada (Hostinger) com MariaDB/MySQL

- **Decisão:** produção no plano compartilhado da Hostinger. Consequências na stack:
  - PostgreSQL → **MariaDB/MySQL** (o plano não oferece PostgreSQL);
  - Redis e Horizon removidos: fila, cache e sessão no **banco** (D16);
  - antivírus fora do escopo (D8).
- *Por quê:* VPS não é viável no momento (decisão do responsável).
- **Consequências:** instância única; sem processos contínuos; desempenho da fila e do cache limitado pelo banco. O código não depende de driver: migrar para VPS = trocar `.env` + reativar Redis/Horizon/ClamAV.
- **Confirmado:** servidor **MariaDB** (`DB_CONNECTION=mariadb`); o plano tem **SSH** (deploy com `composer install` e `migrate` no servidor).

## D15. Edição da resposta pelo respondente (4 horas)

- **Decisão:**
  - **opcional por formulário** (desligada por padrão);
  - janela **fixa de 4 horas** após o envio;
  - acesso por **link secreto** (token aleatório; no banco só o hash), mostrado na tela de sucesso e no email de cópia;
  - validada contra a **mesma versão** do schema do envio original;
  - cada edição guarda a versão anterior em `submission_revisions` e envia email "resposta atualizada" ao dono;
  - formulário fechado bloqueia a edição;
  - anexos podem ser trocados.
- *Por quê:* definido pelo responsável. O histórico evita que um link vazado apague dados sem rastro.

## D16. Fila processada por cron

- **Decisão:** o cron do hPanel executa `php artisan schedule:run` a cada minuto. O agendador (`routes/console.php`) roda `queue:work --stop-when-empty --max-time=55` com `withoutOverlapping`, um para `emails,default` e outro para `exports`.
- *Por quê:* a hospedagem compartilhada não mantém workers contínuos.
- **Consequências:**
  - latência de até ~1 minuto para emails;
  - sem `pcntl`, o `timeout` dos jobs não é imposto, então jobs devem ser curtos e trabalhar em lotes.

## D17. ULID como chave primária das entidades expostas

- **Decisão:** `users` (e depois `forms` e `submissions`) usam ULID como chave primária; `sessions.user_id` e `media.model_id` acompanham.
- *Por quê:* esses IDs aparecem em links (verificação de email, painel). Com ULID há um só identificador, que não revela quantos registros existem nem permite adivinhar o próximo.

## D18. Autenticação resistente a enumeração de contas

- **Decisão:**
  - **Cadastro** responde sempre `202` com a mesma mensagem. Email novo: cria a conta e envia a verificação. Email verificado: avisa o dono por email ("tentativa de cadastro"). Email não verificado: reenvia a verificação.
  - **Login**: mesma mensagem e tempo parecido para "email inexistente" e "senha errada" (hash descartável quando o usuário não existe).
  - **Esqueci a senha** responde sempre `202`; **redefinição** usa a mesma mensagem para token inválido e email sem conta.
  - Rate limit por IP **e** por email (hash do email na chave).
- *Por quê:* não permitir descobrir quem tem conta (privacidade e prevenção de ataques direcionados).
- **Consequência:** depois do cadastro o usuário não fica logado; a SPA mostra "verifique seu email" e o login é liberado normalmente (verificação só é exigida para publicar).

## D19. Links de email apontam para a SPA

- **Decisão:** verificação e redefinição de senha geram links para `FRONTEND_URL` (`/verificar-email`, `/redefinir-senha`); a SPA chama a API. A verificação usa assinatura **relativa** e **não exige login** (o link pode ser aberto em outro dispositivo).
- **Senhas (cont.):** mínimo 8 e máximo 72 caracteres (limite do bcrypt), com letras e números; em produção, também a checagem de senhas vazadas (HIBP, por k-anonymity). Trocar a senha encerra as outras sessões (`AuthenticateSession` do Sanctum).

## D20. Termos de Uso e Política de Privacidade no cadastro

- **Decisão:**
  - os textos ficam em `api/resources/legal/*.md`, com versão em `config/legal.php`, e são servidos por `GET /api/v1/legal/{terms|privacy}`;
  - o cadastro exige `terms_accepted` (um checkbox para os dois documentos);
  - o aceite é gravado em `legal_acceptances` (documento, versão, data, hash do IP) **na mesma transação** que cria a conta.
- *Por quê:* a LGPD exige prova do consentimento e da versão aceita. Uma tabela, e não colunas em `users`, preserva o histórico quando os textos mudarem.
- **Consequências:**
  - o projeto é **open-source e auto-hospedável**: não há empresa por trás. Os textos são **modelos genéricos** com marcadores `{{chave}}`, preenchidos pelos dados do **operador de cada instância** (`LEGAL_*` no `.env`). Quem instala responde pelos dados coletados (LGPD vale para pessoa física, salvo uso particular sem fins econômicos, art. 4º, I). Dado não configurado aparece no texto como "[não informado pelo operador desta instância]";
  - os textos deixam claro que os autores do software não operam as instâncias. Cada operador deve revisar os textos (de preferência com um advogado) para o seu caso;
  - mudança relevante no texto deve subir a `version`. Exigir novo aceite dos usuários antigos ainda não foi implementado.
- **IP:** o hash usa HMAC-SHA256 com `IP_HASH_SALT` (sem sal dedicado, uma chave derivada da `APP_KEY`). Um hash sem chave seria revertido por força bruta, porque há só ~4 bilhões de IPv4.

## D21. Rascunho, versões e configurações

- **Decisão:**
  - `forms.schema` (mais título e descrição) é o **rascunho**. Publicar copia o conteúdo para `form_versions` (imutável: o model lança exceção em qualquer `update`) e incrementa `schema_version`;
  - `has_unpublished_changes` indica rascunho diferente da última versão. Publicar sem mudanças não cria versão; publicar um formulário `closed` o reabre (D13);
  - **configurações** (`settings`) **não são versionadas**: emails de destino, limites e janela valem na hora, inclusive em formulário no ar;
  - o **slug** é gerado do título com sufixo aleatório de 6 caracteres, editável só até a primeira publicação e único **inclusive entre excluídos** (um link antigo nunca abre outro formulário);
  - a cópia ao respondente é conferida contra o schema **ao salvar as configurações e ao publicar**.
- *Por quê:* respostas guardam a versão com que foram enviadas; mudar uma versão mudaria o significado delas. Separar configurações permite, por exemplo, fechar o limite de respostas sem republicar.

## D22. Schema validado por tipo e normalizado no servidor

- **Decisão:**
  - `FormSchemaRules` gera regras por campo conforme o tipo: opções só em escolha, `file` só em arquivo, cada regra só nos tipos em que se aplica (`RuleType::appliesTo`), parâmetros validados por regra;
  - `FormSchemaNormalizer` reconstrói o schema **só com chaves conhecidas** e expande o preset de rigor (a regra do dono prevalece sobre a do preset);
  - limites: 100 campos, 100 opções, 20 regras por campo, blocklist com 200 itens de até 100 caracteres, schema serializado de até 256 KB;
  - identificadores de campo `^[a-z][a-z0-9_]{0,39}$` (viram chaves das respostas).
- **Armadilha registrada:** o `validated()` do Laravel **reordena listas** quando há regras por índice. O schema é lido do input original (já validado por inteiro) para preservar a ordem de campos, opções e regras (`UpdateFormRequest::formData`).
- **Arquivos:** só categorias fechadas (`pdf`, `image` = jpeg/png/webp, `document`, `spreadsheet`, `text`), sem SVG/HTML/executáveis (D8). Até 10 arquivos de até 10 MB por campo (teto da hospedagem).

## D23. Rotas do dono em `/me/forms`

- **Decisão:** a gestão de formulários fica em `/api/v1/me/forms/{id}`; `/api/v1/forms/{slug}` fica reservado para a rota pública (AGENTS.md §8).
- **Autorização:** `FormPolicy` em todas as rotas, via `can:`. Formulário de outra pessoa responde **404** (`denyAsNotFound`), não 403, para não confirmar que o ID existe.

## D3 (complemento). Erros de domínio com mensagem própria

- `App\Exceptions\ApiException` (ex.: "Confirme seu email para publicar", 403; "Só formulários publicados podem ser fechados", 409) mantém a mensagem mesmo em status genéricos. Só mensagens escritas por nós passam; as do framework continuam substituídas.

## D24. Motor de validação anti-lixo

- **Peças:** `InputNormalizer` (normalização) → `RuleRegistry` (um `RuleType` = uma classe `AnswerRule`; `match` exaustivo) → `FormSubmissionValidator` (traduz o schema **publicado** em regras do Laravel e devolve as respostas normalizadas).
- **Normalização é gravada:** o valor salvo é o normalizado (NFC, sem invisíveis/controle, espaços colapsados; `long_text` mantém quebras, até uma linha em branco seguida). Assim, o que foi validado é exatamente o que se armazena e se exporta.
- **Heurísticas julgam a resposta inteira, nunca trechos:** `no_sequences` e `blocklist` rejeitam "12345" ou "teste", mas aceitam "Rua Abc, 1234" e "Contestei a cobrança". Comparação sem maiúsculas e acentos; `no_repeated_chars` não julga respostas com menos de 3 caracteres ("Ok", "Lu").
- **Mensagem genérica** em todas as heurísticas ("Esta resposta não parece válida…"), para não ensinar a burlar a regra (AGENTS.md §5.6). Regras objetivas (tamanho, palavras, CPF…) explicam o que falta. O dono pode definir mensagem própria por regra.
- **CPF/CNPJ/telefone/CEP:** o cálculo é do `pt-br-validator` (AGENTS.md §5.7). Antes de chamar o pacote: só dígitos e separadores (o pacote descartaria letras e aceitaria "CPF abc 123…"); telefone e CEP aceitam qualquer máscara comum e são remontados no formato que o pacote exige. Extras: CEP e telefone com dígitos todos iguais, DDD começando em 0 e celular de 11 dígitos sem o 9 são recusados. CNPJ alfanumérico (2026) aceito.
- **Tipos:** número `^-?\d{1,15}(\.\d{1,6})?$` (vírgula aceita na entrada, gravado como texto: sem perda de precisão), data `Y-m-d`, escolha única/múltipla restritas às opções (múltipla sem repetição, ordem preservada).
- **Segurança e custo:** nenhuma regex vem do schema; regras lineares; texto acima de 10.000 caracteres é recusado antes das regras (`bail`); um erro por campo. Teste garante a validação do pior caso (texto no limite com todas as regras) em menos de 500 ms.
- **Contrato com o front:** `fixtures/validation-cases.json` (formato em `fixtures/README.md`), com seções `normalization`, `rules` e `fields`. Teste garante que toda regra do catálogo tem caso válido e inválido.
- **Requer** as extensões `intl` (Normalizer) e `mbstring` no servidor.

## D25. Recebimento de respostas pela rota pública

- **Decisão:**
  - `GET /forms/{slug}` e `POST /forms/{slug}/submissions` resolvem o slug **só entre formulários já publicados** (rascunho nunca publicado e excluído = 404, igual a inexistente);
  - o schema usado é sempre o da **versão publicada** (cache por formulário + versão), nunca o enviado pelo cliente;
  - **disponibilidade** (`FormAvailability`): fechado, ainda não abriu, prazo encerrado ou limite atingido → `409` com mensagem própria. A página pública recebe `accepts_submissions` + motivo e **não recebe os campos** quando fechado;
  - o **limite de respostas** é conferido dentro de uma transação com o formulário travado (`lockForUpdate`) e um contador `forms.submissions_count`: sem corrida e sem `COUNT(*)` por envio;
  - o evento `SubmissionReceived` é disparado **depois do commit**, para os emails (fase 6).
- **Cache sem invalidação:** a chave é `form-version:{id}:{versão}` e versões são imutáveis, então nunca há cache velho. Status e configurações vêm da linha do formulário (consulta por índice). Com o driver `database` da hospedagem o ganho é pequeno; com Redis (VPS) passa a valer.
- **Anti-abuso:**
  - **honeypot** `website`: preenchido → `201` com recibo falso no mesmo formato, nada gravado (o bot não percebe);
  - **rate limit**: 10/min por IP no formulário, 200/h por IP no geral, 300/min por formulário;
  - **corpo** limitado a 1 MB (`413`), no máximo 200 chaves em `answers`, chaves desconhecidas ignoradas;
  - **CAPTCHA**: contrato `CaptchaVerifier`, com implementação nula até ser decidido (trocar o binding no `AppServiceProvider`).
- **LGPD:** só o HMAC do IP; user agent truncado em 255 caracteres.
- **Pendente da fase 7:** campos de arquivo (inclusive obrigatórios) ainda não são exigidos no envio.

## D26. Idempotência do envio

- **Decisão:**
  - cabeçalho opcional `Idempotency-Key` (8–128 caracteres); no banco só o SHA-256 de `form_id|chave`, com índice único `(form_id, idempotency_key)`;
  - lock atômico do cache por chave: dois envios simultâneos esperam um pelo outro (`409` se o primeiro demorar mais de 5 s);
  - o recibo fica **criptografado** no cache por 24 h (contém o link de edição) e é devolvido igual nos reenvios, **mesmo que o formulário tenha fechado depois** do primeiro envio;
  - cache perdido: o índice único impede duplicar e o recibo é refeito sem o link de edição, porque o token puro não é recuperável.

## D27. Link de edição do respondente

- **Decisão:**
  - token aleatório de 48 caracteres (~285 bits); no banco só o SHA-256;
  - URL da SPA `/f/{slug}/respostas/{id}/editar#token=…`. O token vai no **fragmento** (`#`), que o navegador não envia ao servidor: não aparece em logs de acesso nem no `Referer`. A SPA repassa o token à API no cabeçalho `X-Edit-Token`;
  - token errado ou ausente → `404`; mais de 4 h → `410`; dono desligou a edição, fechou o formulário ou o prazo terminou → `409`. O limite de respostas **não** bloqueia a edição;
  - validação contra a **versão original** da resposta; cada edição guarda o conteúdo anterior em `submission_revisions` (imutável) e dispara `SubmissionEdited`.

## D28. Emails de resposta pela fila

- **Fluxo:** `SubmissionReceived` / `SubmissionEdited` (após o commit) → listener `QueueSubmissionEmails` só enfileira → Jobs na fila `emails` enviam: `SendNewSubmissionEmail` (dono), `SendRespondentCopyEmail` (cópia) e `SendSubmissionUpdatedEmail` (edição).
- **Jobs, não Mailables enfileirados:** o Job concentra retentativas (5, com espera de 10 s → 15 min), `failed()` com log **sem dados pessoais**, `WithoutOverlapping` por resposta e a **idempotência**: confere uma marca antes de enviar e grava logo depois (`owner_notified_at`, `respondent_notified_at`; edições, por marca no cache com a data da edição). Recebe só o ID e relê do banco: resposta ou formulário excluídos nesse meio-tempo → nada é enviado.
- **HTML simples, não Markdown mail:** no Markdown mail o texto escapado ainda passa pelo parser de Markdown, e `[Clique aqui](http://golpe)` digitado por um respondente viraria um link real no email do dono. Todo conteúdo do respondente sai com `{{ }}`; texto longo com `nl2br(e())`. A versão texto puro sai sem entidades (é exibida como texto). O assunto remove quebras de linha do título (sem injeção de cabeçalho).
- **Cópia ao respondente:**
  - vai para o campo de email escolhido pelo dono;
  - sem links de anexos (o link poderia ser repassado);
  - com o link de edição quando ativo. O Job é **criptografado na fila** (`ShouldBeEncrypted`), porque leva o token puro e a fila fica no banco;
  - **limite de 3 cópias por endereço por hora**, somando todos os formulários: quem responde digita o email, então sem limite o formulário serviria para mandar spam a terceiros.
- **Produção:** SMTP do provedor escolhido (D29).

## D29. Email por SMTP genérico, sem provedor fixo

- **Decisão:** usar o transporte `smtp` nativo do Laravel. Removidos `symfony/postmark-mailer` e `symfony/http-client`.
- *Por quê:* o Postmark é pago (o plano grátis é de ~100 emails/mês). Com SMTP, o operador da instância escolhe qualquer provedor só pelo `.env`, sem dependência nova (bom para um projeto open-source).
- **Recomendado:** Brevo (grátis até 300 emails/dia, `smtp-relay.brevo.com:587`). Alternativas: Resend (3.000/mês, 100/dia) ou o email da própria hospedagem (limite baixo, entrega pior).
- **Consequências:**
  - o limite diário do provedor é o teto de emails da instância. Barreira B14 (resumo de notificações) e B13 (confirmar emails extras) ficam mais importantes;
  - SPF, DKIM e DMARC do domínio de envio devem ser configurados no provedor.

## D30. Anexos

- **Envio** (decisão tomada no briefing; AGENTS.md §8): arquivos **na mesma requisição** da resposta, em `multipart/form-data`: respostas em `answers` (texto JSON) e arquivos em `files[<id do campo>][]`. Sem anexos, o envio continua aceitando JSON puro. Na edição, POST com `_method=PUT` (o PHP só lê multipart em POST).
- **Validação** (`FileAnswersValidator`, junto com as respostas: todos os erros de uma vez):
  - **tipo real pelo conteúdo**, com `finfo` direto no arquivo. Nunca extensão, Content-Type do cliente ou `getMimeType()` (que pode ser sobrescrito: o arquivo falso dos testes responde pela extensão);
  - só as categorias fechadas de `FileCategory`. Única exceção: CSV, que não tem assinatura binária e é aceito como `text/plain` + extensão `.csv`;
  - tamanho por arquivo, quantidade por campo e soma por envio (`SUBMISSION_MAX_TOTAL_UPLOAD_MB`, padrão 25);
  - arquivo ilegível (ex.: bloqueado pelo antivírus do servidor) é **recusado**, nunca vira erro 500.
- **Armazenamento** (`AttachmentStorage`):
  - uma coleção do MediaLibrary por campo (`field_<id>`), no disco privado `MEDIA_DISK`;
  - nome em disco UUID + **extensão do tipo real**; nome original limpo (sem caminho, controle e extensão enganosa) só em `name`;
  - **nenhuma conversão** (o conteúdo não é processado);
  - gravado por último na transação; se ela falhar, os arquivos já gravados são apagados do disco.
- **Edição:** enviar arquivos para um campo substitui os anteriores; não enviar mantém. Os antigos são apagados só **depois do commit**, e seus metadados (nome, tamanho, tipo) ficam em `submission_revisions.files`.
- **Download** (`GET /api/v1/files/{uuid}`):
  - só com **link assinado** (`signed`) e dentro da validade (`ATTACHMENT_LINK_TTL_DAYS`, padrão 7);
  - pelo UUID do anexo, não pelo ID sequencial;
  - resposta ou formulário excluídos → 404;
  - entrega sempre como `attachment`, `Content-Type` do tipo real, `nosniff`, CSP `sandbox`, `no-store`.
  - O disco `local` teve `serve` desligado: nenhum arquivo sai por rota automática do framework.
- **Emails:** o dono recebe os links assinados (com aviso de que não há antivírus); a cópia ao respondente lista só os nomes (D28).
- **Hostinger:** `upload_max_filesize` e `post_max_size` do PHP (hPanel → Configuração do PHP) devem comportar o limite total.

## D31. Painel de respostas, exportação e retenção

- **Listagem** (`GET /me/submissions`):
  - sempre restrita por subconsulta aos formulários **do usuário logado e não excluídos**;
  - filtro por formulário validado contra os formulários do próprio dono (`422` se for de outro);
  - período com data pura = dia inteiro em Brasília;
  - ordem por `id` (ULID = ordem de envio), paginação por cursor (≤ 50);
  - `withCount('media')` e `with('form')` contra N+1.
- **Detalhe:** respostas legíveis (rótulos da versão de envio), anexos com link assinado e histórico de edições. `SubmissionPolicy`: resposta de outro dono ou de formulário excluído → `404`.
- **Exclusão** (`DeleteSubmission`): sempre pelo model (o MediaLibrary apaga os arquivos) e decrementa `forms.submissions_count` na mesma transação, sem passar de zero.
- **Exportação CSV:**
  - pedido → `202` e Job na fila `exports`; até 3 exportações em andamento por usuário (`409`) e 10 pedidos por hora;
  - leitura em lotes (`lazyById`) num stream temporário: memória estável;
  - **colunas de todas as versões** publicadas, para não perder campos que deixaram de existir;
  - **formato do Excel em português**: `;` e BOM UTF-8;
  - **CSV injection**: valor iniciado por `= + - @` tab ou CR ganha `'` na frente (inclui números negativos: preço de nunca executar fórmula de respondente);
  - arquivo em disco privado, apagado após 24 h (`PurgeExpiredExports`, de hora em hora); link de download assinado e curto (60 min), regerado a cada consulta.
- **Conexão dos Jobs longos** vem da configuração (`QUEUE_LONG_CONNECTION`, padrão `database-long`): migrar para Redis não exige mudar código, e os testes usam `sync`.
- **Retenção** (`ApplyRetentionPolicy`, diariamente às 03:00 de Brasília):
  - remove respostas mais antigas que `settings.retention_days`, **também em formulários excluídos**, sempre pelo model (anexos inclusos);
  - no máximo 500 por execução; se sobrar, o Job se reenfileira (Jobs curtos, D16).

## D32. Escopo do front e ajustes de contrato (definidos em 2026-10-24)

- **Lógica condicional entra:** condição simples (campo, operador, valor) com ação mostrar/ocultar campo. Pular para etapa fica de fora. Operadores numéricos (`<=`, `>=`, …) incluídos.
  - O **back-end avalia a visibilidade** pelo schema salvo e pelas respostas enviadas, nunca pelo que o cliente diz. Campo oculto não é validado, não é obrigatório e **tem o valor descartado** (inclusive arquivo).
  - Ao salvar: detectar **ciclos**, referências a campos inexistentes e limitar o número de regras.
  - Avaliador de visibilidade em PHP **e** TypeScript, com casos nas `/fixtures` para os dois lados.
- **Itens do schema ganham `kind`** (`input` ou `block`). Blocos (Título de seção, Texto informativo, Divisor, Quebra de etapa) não têm regras, não são obrigatórios e não geram valor.
- **Etapas:** a quebra de etapa é um bloco na lista plana de campos; o renderer deriva as etapas dela (uma só representação, arrastar simples, back-end valida a mesma lista).
- **Paleta:** Avançados = CPF, CNPJ e CEP, cada um já com a regra correspondente. "Texto longo" removido (duplica Parágrafo).
- **Tipos novos:** `rating` (estrelas, de 3 a 10) e `scale` (mínimo e máximo, rótulos opcionais, ex.: 0 a 10). Valor inteiro dentro do intervalo, validado no back-end.
- **Dashboard:** Ativos = `published`, Rascunhos = `draft`, Arquivados = `closed`. Nesta versão "Arquivar" = **fechar** o formulário, e a interface diz isso. Arquivar sem fechar exigiria um `archived_at` no futuro. A listagem ganha `submissions_count` e `last_submission_at` (`withCount`/`withMax`, sem N+1).
- **Tema do formulário versionado:** dentro do schema; o público só vê ao publicar. Imagens de capa e logo são **imutáveis por versão** (trocar no rascunho não afeta a versão publicada) e só podem ser apagadas quando nenhuma versão as referenciar. São **públicas**, diferente dos anexos de respostas (privados e assinados).
- **Landing:** sem "Preços" e "Blog". "Recursos" e "GitHub" (repositório público confirmado: github.com/askuovye/Formix).

## D33. Implementação dos ajustes de contrato (D32)

- **Schema:** cada item tem `kind`. `SchemaItems::inputs()` é o único jeito de ler "campos que geram resposta", usado pelo validador, apresentador, CSV e consistência. Itens sem `kind` são `input` (schemas antigos).
- **Blocos:** `section_title` (texto ≤ 200), `text` (≤ 2000), `divider`, `page_break`. Toda chave de campo (`label`, `required`, `rules`, `visibility`…) é **proibida** em bloco. `page_break` não pode ser o primeiro, o último nem vir logo após outro.
- **`rating`** (`rating.max` 3–10, padrão 5) e **`scale`** (`min` 0–9, `max` ≤ 10 e > `min`, rótulos ≤ 40): resposta inteira validada com `integer` + `between`; texto só com dígitos é aceito e gravado como inteiro.
- **Lógica condicional:**
  - formato `visibility: { action: show|hide, when: { field, operator, value? } }`, só em campos;
  - **a origem precisa vir antes do campo**: com isso ciclos são impossíveis (A↔B é recusado) e a condição só depende de respostas já dadas (combina com etapas);
  - operadores por tipo de origem em `VisibilityOperator::forSource`; origem não pode ser bloco nem arquivo; valor conferido contra o tipo (número, data, opção existente, texto ≤ 200); no máximo 50 condições por formulário;
  - `VisibilityEvaluator` (PHP) percorre os campos em ordem; origem oculta conta como não respondida; comparação com origem não respondida é falsa. O **envio** calcula a visibilidade pelo schema salvo e pelas respostas: campo oculto não é validado nem exigido e fica `null`; arquivos de campo oculto são descartados (inclusive os que já existiam, quando uma edição oculta o campo);
  - apresentador (emails, painel, CSV) **não mostra** campos que estavam ocultos.
  - Contrato com o front: seção `visibility` das fixtures (16 casos).
- **Tema:** `schema.theme`, normalizado com os padrões do "Clássico" (`FormTheme::DEFAULTS`); cores `#RRGGBB` gravadas em maiúsculas; fontes só locais/sistema.
- **Imagens do tema** (`form_assets`):
  - públicas por `GET /api/v1/assets/{id}` (cache de 1 ano `immutable`, `nosniff`, CSP `sandbox`), tipo real por conteúdo (JPG/PNG/WebP), até 5 MB e 20 por formulário;
  - **imutáveis** (o model recusa `update`); o tema só aponta para imagens do próprio formulário e do tipo certo (capa/logo);
  - apagar só se nem o rascunho nem nenhuma versão referencia (busca exata do ULID no JSON das versões); órfãs com mais de 24 h são apagadas diariamente (`PurgeOrphanFormAssets`);
  - as respostas da API acrescentam `url` em `theme.cover`/`theme.logo`.
- **Detecção de tipo real** extraída para `FileTypeDetector` (anexos e imagens usam a mesma).
- **Listagem de formulários:** `submissions_count` (coluna já mantida no envio) e `last_submission_at` (`withMax`, sem N+1).

## D34. Textos legais também em HTML (2026-10-26)

- **Decisão:** `GET /legal/{terms|privacy}` passa a devolver `content_html` além de `content_markdown`. O HTML é gerado no servidor por `Str::markdown` (league/commonmark, já incluso no Laravel) com `html_input: escape` e `allow_unsafe_links: false`.
- *Por quê:* a SPA só exibe o texto, sem incluir um conversor de Markdown no bundle nem escrever um próprio. O texto continua vindo da API, com os dados do operador e a versão ligada ao aceite (D20).
- **Segurança:** HTML vindo do texto ou do `.env` é escapado (aparece como texto) e links `javascript:`/`data:` são descartados; por isso a SPA pode usar `v-html` **só neste campo**, que vem da própria API e não de usuários.
