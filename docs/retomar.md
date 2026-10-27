# Retomar o projeto

Onde paramos, o que vem a seguir e como testar localmente. **Atualize este arquivo ao fim de cada fase.**

## Onde paramos (2026-10-27)

- **Back-end (`/api`): completo.** Fases 1 a 9 do briefing + ajustes de contrato pedidos pelo front (D32/D33): blocos, etapas, estrelas/escala, lógica condicional, tema versionado e imagens de capa/logo; a edição pelo respondente devolve os anexos atuais (nome e tamanho); busca `q` em `/me/forms` (título) e `/me/submissions` (coluna `search_text`, sem acentos). 613 testes Pest, Larastan nível 8, `composer audit` limpo. **Docker validado (2026-10-23):** WSL2 + Docker Desktop instalados, `compose.yaml` sobe, migrations rodam e os testes passam no MariaDB (B5 resolvida) e em SQLite no container (613, ~3,5 min). Os textos legais também saem em HTML (`content_html`, D34).
- **Front (`/web`): fase 1 (fundação) concluída** na `feature/web-fundacao`, já mergeada no `develop`. 51 testes Vitest; `vue-tsc`, ESLint e Prettier limpos.
  - **Tokens e temas** (`styles/tokens`, `styles/themes`): escuro é o padrão, claro projetado à parte, sem flash (script inline no `index.html` + `useThemeStore`). Um tema define só as cores-base; o resto é derivado pelo mixin `color-roles`, que o renderer vai reaplicar com as cores do dono do formulário (fase 4).
  - **Inter local**, **ícones Lucide offline** (só os listados em `components/icons/iconNames.ts`; ícone novo = adicionar o nome + `npm run icons`) e **`motion/`** (tokens espelhando o CSS, com teste de sincronia, e presets `fade`/`slideUp`/`scaleIn`).
  - **Componentes base:** botões, campos, checkbox, radio, switch, badge, card, select, abas, menu, dialog, tooltip e seletor de tema. Os overlays animam entrada e saída com Motion.
  - **`/styleguide`** (só em desenvolvimento): todos os componentes e estados, cores, tipografia, ícones e os presets de movimento. Rodar com `npm run dev` em `web/`.
- **Front: fase 2 (app shell e autenticação) concluída** na `feature/web-shell`, mergeada no `develop`. 125 testes Vitest; `vue-tsc`, ESLint, Prettier e build limpos.
  - **Páginas em `views/`** (uma por rota) e **layouts** em `layouts/`: painel com barra lateral (Formulários e Configurações, ainda vazias), menu do usuário e tema; área pública com Termos, Privacidade (HTML da API) e 404.
  - **Autenticação** (Sanctum SPA: cookie + CSRF): entrar, criar conta (com barra de força da senha), esqueci a senha, nova senha e confirmação de email. Rotas do painel exigem login e guardam o destino; validação Valibot espelha os Form Requests; erros 422 vão para os campos.
  - **Toasts** próprios (`useToastStore`) e aviso de sessão expirada.
- **Front: fase 3 (builder) concluída** em quatro partes (3a a 3d), todas mergeadas no `develop`. 236 testes Vitest; `vue-tsc`, ESLint, Prettier e build limpos.
  - **3a, lista de formulários:** criar, duplicar, arquivar e excluir, com status e paginação.
  - **3b, núcleo do builder:** canvas com arrastar e soltar (vuedraggable), paleta, desfazer e refazer, e salvamento automático (`useAutosave`). Um único salvamento manda só o que mudou: o schema e/ou os dados do formulário.
  - **3c, propriedades completas:** todos os tipos de campo, regras anti-lixo e rigor, com a validação local espelhando o servidor (`schemaErrors.ts`). Os erros 422 vão para os campos.
  - **3d-1:** rail de seções (Campos, Elementos, Lógica, Design, Configurações), aba "Formulário" (título, descrição, slug e link público), configurações (emails, limites, datas, cópia ao respondente, edição, retenção), e publicar, fechar e reabrir. No desktop, os painéis ficam recolhidos e deslizam ao chegar perto da borda (`useEdgeReveal`); selecionar um campo abre as propriedades. No celular, gavetas com botão de menu e botão flutuante de adicionar.
  - **3d-2, lógica condicional:** seção "Lógica" nas propriedades do campo, frase da condição no card e visão geral no rail. `visibility.ts` espelha as regras do back-end (origem anterior, operador por tipo, valor, limite de 50).
  - **Arrastar no builder (ajuste pós-fase 3):** o card inteiro arrasta (os botões de ação não); `BuilderDragLift` mostra um card levantado que segue o ponteiro com leve atraso, inclina pela velocidade e tem sombra em paralaxe; vaga tracejada; no toque, segurar um instante antes de arrastar. Um rAF só durante o arrasto, só `transform`/`opacity`.
- **Front: fase 4 (renderer público) concluída** em cinco partes (4a a 4e), todas mergeadas no `develop`. 462 testes Vitest; `vue-tsc`, ESLint, Prettier e build limpos.
  - **4a, motor de validação em TS** (`features/validation`): normalização, catálogo de regras e avaliador de visibilidade, passando pelas mesmas fixtures do back-end.
  - **4b, página pública** `/f/:slug`: etapas, lógica condicional, honeypot, chave de idempotência, erros 422 nos campos com foco no primeiro, situações 404/409/429 e comprovante.
  - **4c, tema do dono:** `FormThemeScope` aplica cores, fonte, cantos, botão, alinhamento, capa e logo (página pública, canvas do builder e prévia). Seção **Design** no builder (presets Clássico/Minimalista/Escuro/Moderno/Personalizado, aviso de contraste, envio de capa e logo, tudo no desfazer). **Visualizar** abre a prévia do rascunho numa janela dentro do builder (desktop e celular). Sem tema = Clássico (branco).
  - **4d, anexos:** núcleo do Uppy (`@uppy/core`, carregado sob demanda) com UI própria; regras espelhando o `FileAnswersValidator` (`files.ts`); miniatura das imagens; envio multipart com progresso no botão.
  - **4e, edição pelo respondente** `/f/:slug/respostas/:id/editar#token=…`: formulário preenchido, anexos atuais como "Enviado" (escolher novos substitui), recusas 404/409/410 com motivo.
- **Front: fase 5 em andamento** na `feature/web-submissions` (com push; **ainda não mergeada**: falta o usuário validar). 473 testes Vitest; `vue-tsc`, ESLint, Prettier e build limpos.
  - **5a, painel de respostas** `/painel/respostas`: lista (cursor) + detalhe ao lado no desktop, um de cada vez no celular; filtros de formulário, período e ordem na URL; respostas, anexos por link assinado, histórico de edições e exclusão. Atalhos "Ver respostas" na lista de formulários e no menu de publicar do builder.
  - **Extras pedidos (2026-10-27):** prévia das imagens anexadas (clicar amplia numa janela; outros tipos com ícone); **busca** em Formulários e Respostas (`?busca=`, com espera depois da digitação, `useUrlSearch`); **troca de tema em círculo** que nasce no centro e cobre a tela (`composables/circleReveal.ts`, View Transitions API; as transições dos componentes ficam zeradas durante a troca por `[data-theme-switching]`).
- **Ambiente de dev mais rápido (2026-10-26):** o `vendor/` dos containers fica num volume do Docker e o servidor roda com 4 workers. As requisições à API caíram de 3 a 11 s para 0,15 a 0,3 s.
- **Git:** github.com/askuovye/Formix, gitflow. `develop` tem tudo e está no GitHub; `main` está no commit inicial.

## Próximo passo

**Validar com o usuário a `feature/web-submissions`** (5a + extras) e mergear no `develop`. Depois, **5b: exportação CSV** (`POST /me/forms/{id}/exports` já existe no back; acompanhar o status e baixar) e **5c: resultados** (endpoint novo `GET /me/forms/{id}/results` com agregação no servidor + contadores e gráficos com GSAP). Confirmar o plano de cada parte antes de codar. Depois: fase 6 (landing), fase 7 (revisão).

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
| Testes (SQLite) | `docker compose exec -e COMPOSER_PROCESS_TIMEOUT=0 app composer test` | 613 passando |
| **Testes no MariaDB** (barreira B5) | comando abaixo | 613 passando |

No Windows a suíte leva ~3 min no container (o código ainda passa pela pasta compartilhada, que é lenta); por isso o `COMPOSER_PROCESS_TIMEOUT=0`, senão o Composer corta em 300 s. Mudou o front e algo sumiu da tela? Reinicie o `npm run dev` (depois de mover arquivos ou instalar pacotes o Vite pode ficar com cache velho).

```powershell
docker compose exec -e DB_CONNECTION=mariadb -e DB_HOST=database -e DB_DATABASE=testing app php artisan test
```

**Cadastro de conta pela API** (sem passar pelo front): o email de verificação aparece no Mailpit.

```powershell
curl.exe -X POST http://localhost:8000/api/v1/auth/register -H "Accept: application/json" -H "Content-Type: application/json" -d '{\"name\":\"Teste Local\",\"email\":\"teste@example.com\",\"password\":\"senhaForte123\",\"password_confirmation\":\"senhaForte123\",\"terms_accepted\":true}'
```

Login, cadastro, confirmação de email e o builder completo já dá para testar pelo front; o preenchimento público e o envio de respostas vêm na fase 4.

## Parar e retomar

```powershell
docker compose stop          # para os containers (dados preservados)
docker compose up -d         # sobe de novo
docker compose down -v       # apaga TUDO, inclusive o banco local (cuidado)
```

## Pedido para o Claude depois de reiniciar

> "Leia `docs/retomar.md` e continue de onde paramos."

Se o Docker já estiver instalado, peça também: "suba a API e rode os testes no MariaDB (B5)".
