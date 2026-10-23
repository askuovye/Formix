# API: construtor de formulários

API JSON em Laravel 13 (PHP 8.4). Regras do projeto: [`AGENTS.md`](../AGENTS.md). Documentação: [`docs/`](../docs/README.md) (contrato da API em [`docs/api.md`](../docs/api.md)).

## Desenvolvimento

Requisitos: Docker com Compose v2 (no Windows: Docker Desktop com WSL2).

```sh
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Gere também o sal do hash de IP (`IP_HASH_SALT` no `.env`):

```sh
docker compose exec app php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

| Serviço | Endereço |
|---|---|
| API | http://localhost:8000/api/v1 |
| Health check | http://localhost:8000/api/v1/health |
| Mailpit (emails) | http://localhost:8025 |

### Filas

Não há worker contínuo (D16). O container `scheduler` roda `schedule:work`, que dispara os workers a cada minuto, como o cron faz em produção. Para processar na hora:

```sh
docker compose exec app php artisan queue:work --queue=emails,default --stop-when-empty
docker compose exec app php artisan queue:work database-long --queue=exports --stop-when-empty
```

### Qualidade

```sh
docker compose exec app composer check     # Pint + Larastan (nível 8) + Pest + composer audit
docker compose exec app composer test      # só os testes
docker compose exec app vendor/bin/pint    # formata o código
```

Os testes usam SQLite em memória e fila síncrona (`phpunit.xml`).

## Produção (Hostinger, hospedagem compartilhada)

Requisitos do plano: PHP 8.4 com `intl`, `mbstring`, `exif`, `pdo_mysql`, `fileinfo` e `zip`; MariaDB; SSH; cron.

1. **PHP** (hPanel → Avançado → Configuração do PHP): `upload_max_filesize` e `post_max_size` ≥ 30M (limite de anexos por envio: 25 MB), `memory_limit` ≥ 256M. Confira as extensões acima.
2. **Código:** envie o projeto por Git/SSH para fora do `public_html` e aponte o domínio da API para `api/public` (ou crie um link simbólico de `public_html` para `api/public`).
3. **Dependências e banco** (via SSH):
   ```sh
   composer install --no-dev --optimize-autoloader
   php artisan key:generate        # só na primeira vez
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
4. **Cron** (hPanel → Avançado → Cron Jobs, a cada minuto):
   ```sh
   cd /home/USUARIO/caminho/api && php artisan schedule:run >> /dev/null 2>&1
   ```
5. **`.env` de produção**, além dos dados do banco:

   | Variável | Valor |
   |---|---|
   | `APP_ENV` / `APP_DEBUG` | `production` / `false` |
   | `APP_URL` / `FRONTEND_URL` | URLs **https** da API e da SPA |
   | `SESSION_DOMAIN` | domínio pai, ex.: `.exemplo.com` |
   | `SANCTUM_STATEFUL_DOMAINS` / `CORS_ALLOWED_ORIGINS` | domínio da SPA (ex.: `app.exemplo.com` / `https://app.exemplo.com`) |
   | `SESSION_SECURE_COOKIE` | `true` |
   | `IP_HASH_SALT` | gerado uma vez e **nunca trocado** |
   | `MAIL_*` | SMTP do provedor escolhido (D29, barreira B15), com SPF/DKIM/DMARC no DNS |
   | `LEGAL_*` | seus dados como operador da instância (Termos e Política) |
   | `TRUSTED_PROXIES` | vazio, salvo se houver proxy/CDN à frente (ex.: Cloudflare) |
   | `LOG_LEVEL` | `warning` |

6. **HTTPS:** ative o SSL do domínio no hPanel. Sem HTTPS, os cookies seguros não funcionam e o HSTS não é enviado.
7. **Backup:** banco **e** `storage/app/private` (anexos). O hPanel faz backups, mas confira se a pasta `storage` está incluída.

A cada deploy: `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force` e os três `cache` do passo 3.

Antes de abrir ao público, veja os itens de prioridade alta em [`docs/barreiras.md`](../docs/barreiras.md) e o checklist em [`docs/security.md`](../docs/security.md).
