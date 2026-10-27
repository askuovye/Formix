# Retomar o projeto

Onde paramos, o que vem a seguir e como testar localmente. **Atualize este arquivo ao fim de cada fase.**

## Onde paramos (2026-10-27)

- **Back-end (`/api`): completo.** Fases 1 a 9 do briefing + ajustes de contrato pedidos pelo front (D32/D33): blocos, etapas, estrelas/escala, lógica condicional, tema versionado e imagens de capa/logo; a edição pelo respondente devolve os anexos atuais (nome e tamanho); busca `q` em `/me/forms` (título) e `/me/submissions` (coluna `search_text`, sem acentos); **PDF das respostas** com dompdf (D35: uma resposta na hora e exportação `format=pdf` até 300) e exportação com busca `q`; **resultados** `GET /me/forms/{id}/results` (agregados por campo, linha do tempo por dia/semana) e filtro **`email`** (respostas de uma pessoa, coluna `submissions.respondent_email`) na lista, nos resultados e na exportação. 630 testes Pest, Larastan nível 8, `composer audit` limpo (PHPUnit 13.4.5). **Docker validado (2026-10-23):** WSL2 + Docker Desktop instalados, `compose.yaml` sobe, migrations rodam e os testes passam no MariaDB (B5 resolvida) e em SQLite no container (622, ~3,5 min). Os textos legais também saem em HTML (`content_html`, D34).
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
- **Front: fase 5 em andamento.** 5a, extras, 5b e **5c** validados e mergeados no `develop` (fase 5 concluída). 491 testes Vitest; `vue-tsc`, ESLint, Prettier e build limpos.
  - **5a, painel de respostas** `/painel/respostas`: lista (cursor) + detalhe ao lado no desktop, um de cada vez no celular; filtros de formulário, período e ordem na URL; respostas, anexos por link assinado, histórico de edições e exclusão. Atalhos "Ver respostas" na lista de formulários e no menu de publicar do builder.
  - **Extras pedidos (2026-10-27):** prévia das imagens anexadas (clicar amplia numa janela; outros tipos com ícone); **busca** em Formulários e Respostas (`?busca=`, com espera depois da digitação, `useUrlSearch`); **troca de tema em círculo** que nasce no centro e cobre a tela (`composables/circleReveal.ts`, View Transitions API; as transições dos componentes ficam zeradas durante a troca por `[data-theme-switching]`).
  - **5b, exportação e PDF:** janela "Exportar respostas" (tela de Respostas, com formulário, período e busca atuais; e menu de cada formulário) com CSV ou PDF; acompanha a fila (`useExportJob`) e, se a janela fechar, `ExportTracker` no painel avisa com "Baixar" quando termina. Botão "Baixar PDF" no detalhe da resposta. O PDF de uma resposta não fica salvo; o arquivo da exportação fica no disco privado e some depois de 24 h (`EXPORT_TTL_HOURS`).
  - **5c, resultados:** abas "Respostas | Resultados" na tela de Respostas (`?aba=resultados`), com os mesmos filtros de período, busca e pessoa; precisa de um formulário escolhido. Totais, respostas por dia (por semana acima de 90 dias), escolhas em barras com %, estrelas/escala com média e distribuição, número (menor/média/maior), data (intervalo), anexos (contagem) e as 5 últimas respostas de texto (clicar abre a resposta). Atualiza sozinho a cada 30 s com a aba visível (TanStack `refetchInterval`; sem WebSocket na hospedagem), sem repetir a animação. Barras crescem e números sobem com GSAP (`useGsapContext`, chunk `ResultsPanel` carregado só na aba). **"Ver todas desta pessoa"** no detalhe filtra lista, resultados e exportação pelo email de quem respondeu (`?pessoa=`), em todos os formulários. Atalho "Ver resultados" no menu de cada formulário.
- **Ambiente de dev mais rápido (2026-10-26):** o `vendor/` dos containers fica num volume do Docker e o servidor roda com 4 workers. As requisições à API caíram de 3 a 11 s para 0,15 a 0,3 s.
- **Git:** github.com/askuovye/Formix, gitflow. `develop` tem tudo e está no GitHub; `main` está no commit inicial.

## Próximo passo

**Fase 6 (landing) concluída:** 6a (estrutura, conteúdo, temas, formulário do hero respondível) e 6b (animações GSAP) mergeadas no `develop`.

**Fase 7: revisão final**, em três partes, com validação do usuário ao fim de cada uma:

- **7a, auditoria técnica** (`feature/review-a11y`, com push, aguardando validação):
  - contraste AA medido e corrigido nos tokens (textos secundários e cores de status);
  - `--color-border-control` (3:1, WCAG 1.4.11) nos contornos de campos de texto, checkbox, rádio, switch e estrelas;
  - axe-core sem violações em todas as telas, nos dois temas (rodado só no navegador de teste, fora do projeto);
  - movimento reduzido conferido; preload da Inter 400/700 no build;
  - breakpoints em TS (`config/breakpoints.ts`); Pest/PHPUnit atualizados.
- **7b:** comparação visual com `design/reference.png` (claro e escuro); divergências de conteúdo vão para decisão do usuário.
- **7c:** documentação (B6/B10 em `barreiras.md`, `decisions.md`) e merge.

**Fase 8: pré-lançamento** (plano aprovado em 2026-10-27):

- B1: denunciar formulário.
- B2: suspender formulário ou conta (comando artisan).
- B13: confirmar os emails extras de notificação.
- B15: SMTP (Brevo; conta e DNS ficam com o usuário).
- Guia de deploy na Hostinger (`docs/deploy.md`).
- Merge `develop` → `main` com a tag `v1.0.0`.

**Deixado para depois (decisão do usuário, 2026-10-27):** reduzir o peso do Motion no bundle principal (~134 KB sem gzip de `motion-v` + `motion-dom`) com `LazyMotion` + componentes `m`, carregando só os recursos usados. Mexe em todos os componentes animados; fazer numa branch própria, depois do lançamento.

Confirmar o plano de cada parte antes de codar.

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
| Testes (SQLite) | `docker compose exec -e COMPOSER_PROCESS_TIMEOUT=0 app composer test` | 622 passando |
| **Testes no MariaDB** (barreira B5) | comando abaixo | 622 passando |

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
