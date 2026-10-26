# Retomar o projeto

Onde paramos, o que vem a seguir e como testar localmente. **Atualize este arquivo ao fim de cada fase.**

## Onde paramos (2026-10-26)

- **Back-end (`/api`): completo.** Fases 1 a 9 do briefing + ajustes de contrato pedidos pelo front (D32/D33): blocos, etapas, estrelas/escala, lógica condicional, tema versionado e imagens de capa/logo. 608 testes Pest, Larastan nível 8, `composer audit` limpo. **Docker validado (2026-10-23):** WSL2 + Docker Desktop instalados, `compose.yaml` sobe, migrations rodam e os testes passam no MariaDB (B5 resolvida) e em SQLite no container (608, ~3 min). Os textos legais também saem em HTML (`content_html`, D34).
- **Front (`/web`): fase 1 (fundação) concluída** na `feature/web-fundacao`, já mergeada no `develop`. 51 testes Vitest; `vue-tsc`, ESLint e Prettier limpos.
  - **Tokens e temas** (`styles/tokens`, `styles/themes`): escuro é o padrão, claro projetado à parte, sem flash (script inline no `index.html` + `useThemeStore`). Um tema define só as cores-base; o resto é derivado pelo mixin `color-roles`, que o renderer vai reaplicar com as cores do dono do formulário (fase 4).
  - **Inter local**, **ícones Lucide offline** (só os listados em `components/icons/iconNames.ts`; ícone novo = adicionar o nome + `npm run icons`) e **`motion/`** (tokens espelhando o CSS, com teste de sincronia, e presets `fade`/`slideUp`/`scaleIn`).
  - **Componentes base:** botões, campos, checkbox, radio, switch, badge, card, select, abas, menu, dialog, tooltip e seletor de tema. Os overlays animam entrada e saída com Motion.
  - **`/styleguide`** (só em desenvolvimento): todos os componentes e estados, cores, tipografia, ícones e os presets de movimento. Rodar com `npm run dev` em `web/`.
- **Front: fase 2 (app shell e autenticação) concluída** na `feature/web-shell`, mergeada no `develop`. 125 testes Vitest; `vue-tsc`, ESLint, Prettier e build limpos.
  - **Páginas em `views/`** (uma por rota) e **layouts** em `layouts/`: painel com barra lateral (Formulários e Configurações, ainda vazias), menu do usuário e tema; área pública com Termos, Privacidade (HTML da API) e 404.
  - **Autenticação** (Sanctum SPA: cookie + CSRF): entrar, criar conta (com barra de força da senha), esqueci a senha, nova senha e confirmação de email. Rotas do painel exigem login e guardam o destino; validação Valibot espelha os Form Requests; erros 422 vão para os campos.
  - **Toasts** próprios (`useToastStore`) e aviso de sessão expirada.
- **Ambiente de dev mais rápido (2026-10-26):** o `vendor/` dos containers fica num volume do Docker e o servidor roda com 4 workers. As requisições à API caíram de 3 a 11 s para 0,15 a 0,3 s.
- **Git:** github.com/askuovye/Formix, gitflow. `develop` tem tudo e está no GitHub; `main` está no commit inicial.

## Próximo passo

**Fase 3 do front: builder** (a mais importante). Depois: fase 4 (renderer, temas e imagem), fase 5 (dashboard e resultados), fase 6 (landing), fase 7 (revisão).

**Leitura para retomar o front:** briefing nas mensagens do usuário (resumido em D32 e no AGENTS.md §2/§7), referência visual em `design/reference.png`, contrato em `api.md` e `frontend-contract-requests.md`, espelho do motor em `../fixtures/README.md`.

**Pendências antes de abrir ao público:** itens de prioridade alta em `barreiras.md` (B1, B2, B6, B10, B13, B15).

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

A primeira vez demora alguns minutos (download das imagens e `composer install` automático no volume `formularios_vendor`). Dependência nova: `docker compose exec app composer require …`. Mudou o `.env`: `docker compose restart app`. Para conferir se está tudo de pé:

```powershell
docker compose ps
```

Os serviços `app`, `scheduler`, `database` e `mailpit` devem aparecer como `running`/`healthy`.

## Testar

| O quê | Como | Esperado |
|---|---|---|
| API no ar | abrir http://localhost:8000/api/v1/health | `{"data":{"status":"ok",…}}` |
| Termos de uso | http://localhost:8000/api/v1/legal/terms | texto em Markdown e em HTML |
| Front | `npm run dev` em `web/` e abrir http://localhost:5173 | tela de entrar |
| Emails | http://localhost:8025 (Mailpit) | caixa de entrada local |
| Testes (SQLite) | `docker compose exec -e COMPOSER_PROCESS_TIMEOUT=0 app composer test` | 608 passando |
| **Testes no MariaDB** (barreira B5) | comando abaixo | 608 passando |

No Windows a suíte leva ~3 min no container (o código ainda passa pela pasta compartilhada, que é lenta); por isso o `COMPOSER_PROCESS_TIMEOUT=0`, senão o Composer corta em 300 s. Mudou o front e algo sumiu da tela? Reinicie o `npm run dev` (depois de mover arquivos ou instalar pacotes o Vite pode ficar com cache velho).

```powershell
docker compose exec -e DB_CONNECTION=mariadb -e DB_HOST=database -e DB_DATABASE=testing app php artisan test
```

**Cadastro de conta pela API** (sem passar pelo front): o email de verificação aparece no Mailpit.

```powershell
curl.exe -X POST http://localhost:8000/api/v1/auth/register -H "Accept: application/json" -H "Content-Type: application/json" -d '{\"name\":\"Teste Local\",\"email\":\"teste@example.com\",\"password\":\"senhaForte123\",\"password_confirmation\":\"senhaForte123\",\"terms_accepted\":true}'
```

Login, cadastro e confirmação de email já dá para testar pelo front; builder e envio de respostas vêm nas próximas fases.

## Parar e retomar

```powershell
docker compose stop          # para os containers (dados preservados)
docker compose up -d         # sobe de novo
docker compose down -v       # apaga TUDO, inclusive o banco local (cuidado)
```

## Pedido para o Claude depois de reiniciar

> "Leia `docs/retomar.md` e continue de onde paramos."

Se o Docker já estiver instalado, peça também: "suba a API e rode os testes no MariaDB (B5)".
