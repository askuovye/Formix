# Retomar o projeto

Onde paramos, o que vem a seguir e como testar localmente. **Atualize este arquivo ao fim de cada fase.**

## Onde paramos (2026-10-25)

- **Back-end (`/api`): completo.** Fases 1 a 9 do briefing + ajustes de contrato pedidos pelo front (D32/D33): blocos, etapas, estrelas/escala, lógica condicional, tema versionado e imagens de capa/logo. 606 testes Pest, Larastan nível 8, `composer audit` limpo. **Testado só em SQLite**: nunca rodou em MariaDB/Docker (barreira B5).
- **Front (`/web`): não começado.** Só o esqueleto (Vue 3, Vite, TS, Pinia, Router, Vitest, ESLint, Prettier, Reka, Iconify, Motion, GSAP). O briefing do front foi aprovado; o AGENTS.md (§2 e §7) já reflete a stack nova.
- **Git:** github.com/askuovye/Formix, gitflow. `develop` tem tudo; `main` está no commit inicial.

## Próximo passo

**Fase 1 do front** na branch `feature/web-fundacao`:
1. tokens (cores extraídas da referência), temas claro e escuro sem flash;
2. Inter local, `AppIcon` com Lucide offline e o módulo de movimento;
3. componentes base sobre Reka UI e a rota `/styleguide`.

Depois: fase 2 (app shell e autenticação), fase 3 (builder, a mais importante), fase 4 (renderer, temas e imagem), fase 5 (dashboard e resultados), fase 6 (landing), fase 7 (revisão).

**Leitura para retomar o front:** briefing nas mensagens do usuário (resumido em D32 e no AGENTS.md §2/§7), referência visual em `design/reference.png`, contrato em `api.md` e `frontend-contract-requests.md`, espelho do motor em `../fixtures/README.md`.

**Pendências antes de abrir ao público:** itens de prioridade alta em `barreiras.md` (B1, B2, B5, B6, B10, B13, B15).

---

## Instalar o Docker (Windows 11)

1. **WSL2.** Abra o PowerShell **como administrador** e rode:
   ```powershell
   wsl --install --no-distribution
   ```
   **Reinicie o computador.**
2. **Docker Desktop.** No PowerShell (pode ser comum):
   ```powershell
   winget install -e --id Docker.DockerDesktop
   ```
   Ou baixe em docker.com/products/docker-desktop. Abra o Docker Desktop uma vez, aceite os termos e espere o ícone ficar verde ("Engine running"). Nas configurações, deixe marcado **"Use the WSL 2 based engine"**.
3. **Conferir:**
   ```powershell
   docker version
   docker compose version
   ```
   Os dois devem responder sem erro.

## Subir a API

Na pasta `api` (o `.env` local já existe, com `APP_KEY` e `IP_HASH_SALT` gerados):

```powershell
cd "C:\Users\Usuario\Documents\Projeto Formulario\api"
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate
```

A primeira vez demora alguns minutos (download das imagens). Para conferir se está tudo de pé:

```powershell
docker compose ps
```

Os serviços `app`, `scheduler`, `database` e `mailpit` devem aparecer como `running`/`healthy`.

## Testar

| O quê | Como | Esperado |
|---|---|---|
| API no ar | abrir http://localhost:8000/api/v1/health | `{"data":{"status":"ok",…}}` |
| Termos de uso | http://localhost:8000/api/v1/legal/terms | texto em Markdown |
| Emails | http://localhost:8025 (Mailpit) | caixa de entrada local |
| Testes (SQLite) | `docker compose exec app composer test` | 606 passando |
| **Testes no MariaDB** (barreira B5) | comando abaixo | idealmente 606 passando; qualquer falha aqui é diferença real entre SQLite e MariaDB e deve ser corrigida |

```powershell
docker compose exec -e DB_CONNECTION=mariadb -e DB_HOST=database -e DB_DATABASE=testing app php artisan test
```

**Cadastro de conta pela API** (sem o front ainda): o email de verificação aparece no Mailpit.

```powershell
curl.exe -X POST http://localhost:8000/api/v1/auth/register -H "Accept: application/json" -H "Content-Type: application/json" -d '{\"name\":\"Teste Local\",\"email\":\"teste@example.com\",\"password\":\"senhaForte123\",\"password_confirmation\":\"senhaForte123\",\"terms_accepted\":true}'
```

O resto do fluxo (login, builder, envio de respostas) depende de sessão por cookie e é mais prático testar quando o front existir.

## Parar e retomar

```powershell
docker compose stop          # para os containers (dados preservados)
docker compose up -d         # sobe de novo
docker compose down -v       # apaga TUDO, inclusive o banco local (cuidado)
```

## Pedido para o Claude depois de reiniciar

> "Leia `docs/retomar.md` e continue de onde paramos."

Se o Docker já estiver instalado, peça também: "suba a API e rode os testes no MariaDB (B5)".
