# Escala

Onde a instância aguenta, onde vai apertar e o que fazer em cada degrau. Ponto de partida: hospedagem compartilhada (D14), uma instância, MariaDB, fila e cache no banco, anexos no disco local.

## Diretrizes do projeto

| Diretriz | Status | Como / ressalva |
|---|---|---|
| Aplicação stateless (sessão, cache e fila fora do processo) | ⚠️ | Sessão, cache e fila no **banco** (compartilhado entre processos). Anexos e CSVs em **disco local**: impede mais de uma instância até migrar para S3-compatível (`MEDIA_DISK`/`EXPORT_DISK`, já suportado) |
| Trabalho lento em Jobs idempotentes, filas por prioridade | ✅ | `emails` > `default` (retenção, limpeza) na conexão `database`; `exports` na conexão longa. Jobs com `tries`, `backoff`, `failed()` e marcas de idempotência |
| Índices para as consultas reais | ✅ | `forms(slug)` único, `forms(user_id, status)`, FK `forms(user_id)` (+ PK implícita) para listar; `submissions(form_id, created_at)`, FK `submissions(form_id)` (+ PK) para listar e paginar, `submissions(form_id, idempotency_key)` único; `exports(user_id, status)` e `exports(expires_at)`. Sem índice em JSON (nenhuma consulta o exige) |
| Evitar N+1 | ✅ | `with()`/`withCount()` nas listagens; `Model::shouldBeStrict()` fora de produção quebra o teste se houver lazy loading |
| Cache do schema publicado, nunca das respostas | ✅ | Cache por `(formulário, versão)`, imutável, sem invalidação (D25). O recibo de idempotência (criptografado) não contém respostas |
| Paginação por cursor em toda listagem | ✅ | Formulários e respostas (≤ 50 por página). Nenhuma rota devolve lista ilimitada |
| Escrita principal enxuta + Jobs | ✅ | Envio = 1 transação (formulário travado, resposta, contador, anexos); emails depois do commit |
| Transações e Jobs `afterCommit` | ✅ | Conexões de fila com `after_commit = true`; eventos de resposta com `ShouldDispatchAfterCommit` |
| Pronto para várias instâncias e workers | ⚠️ | Código pronto (locks atômicos de cache, idempotência por índice único, `withoutOverlapping`). Falta storage compartilhado e, de preferência, Redis |
| Particionar `submissions` por data no futuro | ✅ (desenho) | Ver "Degrau 3" |

## Gargalos previstos, em ordem

1. **Fila por cron (D16):** latência de até ~1 min e um worker por fila. Uma rajada de respostas enfileira emails que saem aos poucos.
   *Sinal:* `degraded` no `/api/v1/health` (job parado > 10 min).
2. **Limite diário do provedor SMTP** (ex.: 300/dia no Brevo grátis): um formulário popular esgota os emails da instância inteira.
   *Resposta:* B14 (resumo) e/ou plano pago do provedor.
3. **Fila, cache e sessão no mesmo MariaDB:** cada requisição autenticada lê a sessão no banco; cada envio grava em `jobs`; o rate limit escreve em `cache`. É o primeiro ponto de contenção sob carga.
4. **Trava por formulário no envio** (`lockForUpdate`): envios ao **mesmo** formulário são serializados. Com transação curta isso aguenta dezenas por segundo por formulário; um formulário viral é o caso extremo.
5. **Disco local:** espaço do plano e backup dos anexos; impede escalar horizontalmente.
6. **Tabela `submissions` muito grande** (dezenas de milhões de linhas): índices maiores, exportações e retenção mais lentas.

## Degraus

### Degrau 1: VPS única (sem mudar código)

- Redis para sessão, cache e fila: `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, e uma conexão `redis-long` com `retry_after` alto para `QUEUE_LONG_CONNECTION`.
- Workers contínuos com Horizon (reinstalar o pacote) ou `supervisord` com `queue:work`; `pcntl` disponível passa a impor o `timeout` dos Jobs.
- `clamd` + antivírus nos anexos (desenho em D8, B4).
- PHP-FPM com OPcache e o servidor web de sua escolha à frente.

### Degrau 2: várias instâncias da aplicação

- Anexos e exportações em S3-compatível (`MEDIA_DISK=s3`, `EXPORT_DISK=s3`; `league/flysystem-aws-s3-v3` já instalado). A rota de download continua a mesma (lê do disco configurado).
- Load balancer com `TRUSTED_PROXIES` configurado (o IP real alimenta rate limit e hash de IP).
- Redis compartilhado (sessão, cache, locks e fila); agendador (`schedule:run`) em **uma** instância só, ou `onOneServer()`.
- Banco gerenciado com réplica de leitura para listagens e exportações (`DB::connection('read')` nas consultas do painel).

### Degrau 3: volume muito alto de respostas

- **Particionar `submissions` por data** (`PARTITION BY RANGE` em `created_at`, mensal). Pré-requisitos já atendidos pelo desenho: PK ULID (ordenada no tempo), consultas do painel sempre por `form_id` + período, retenção apagando por data. Ajuste necessário: no MariaDB a chave de partição precisa estar em todas as chaves únicas, então a PK vira `(id, created_at)` e o único de idempotência inclui `created_at` (ou passa para uma tabela própria com TTL).
- Contador `submissions_count` já evita `COUNT(*)`; a listagem por cursor não sofre com o tamanho.
- Exportações muito grandes: gerar por partes (um arquivo por mês) e compactar.
- Respostas antigas em armazenamento frio (arquivar e apagar do banco quente).

## Como medir antes de mudar

- `GET /api/v1/health`: estado do banco e atraso da fila.
- Tamanho das tabelas `submissions`, `jobs`, `cache` e `sessions` (as três últimas devem ficar pequenas; se crescerem, a limpeza ou o processamento está atrasado).
- Tempo de resposta do `POST /forms/{slug}/submissions` no log do servidor web.
- Uso de disco de `storage/app/private`.
