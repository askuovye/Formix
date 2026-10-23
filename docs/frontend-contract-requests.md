# Pedidos de contrato do front

Lacunas que o front precisou do back-end, com o formato acordado. Status: **implementado** na branch `feature/api-contrato-front` (decisão D32, detalhes em D33). O contrato completo continua em [`api.md`](api.md).

## 1. Itens do schema: `kind` (input ou bloco)

```ts
type SchemaItem = InputField | BlockItem

interface BlockItem {
  id: string                       // mesmo padrão dos campos, único no formulário
  kind: 'block'
  type: 'section_title' | 'text' | 'divider' | 'page_break'
  text?: string                    // section_title: até 200; text: até 2000; divider/page_break: ausente
}
```

- Blocos não têm `label`, regras, `required`, visibilidade nem valor na resposta.
- **Etapas:** o renderer divide a lista em etapas a cada `page_break`. Um `page_break` não pode ser o primeiro nem o último item, nem vir logo depois de outro (nenhuma etapa vazia).
- Itens sem `kind` são tratados como `input` (compatibilidade).

## 2. Tipos `rating` e `scale`

```ts
interface RatingField extends InputBase { type: 'rating'; rating: { max: number } }          // max 3–10 (padrão 5)
interface ScaleField  extends InputBase { type: 'scale';  scale: { min: number; max: number; min_label: string | null; max_label: string | null } }
// scale: min 0–9, max até 10 e maior que min; rótulos até 40 caracteres
```

Resposta: **inteiro** dentro do intervalo (`1..max` em `rating`; `min..max` em `scale`). Aceita número ou texto numérico; gravado como inteiro.

## 3. Lógica condicional (mostrar/ocultar)

```ts
interface Visibility {
  action: 'show' | 'hide'          // show: visível só se a condição for verdadeira; hide: oculto se for verdadeira
  when: { field: string; operator: Operator; value?: string | number }
}
type Operator = 'eq' | 'neq' | 'lt' | 'lte' | 'gt' | 'gte' | 'includes' | 'excludes' | 'answered' | 'not_answered'
```

| Campo de origem | Operadores | `value` |
|---|---|---|
| `number`, `rating`, `scale` | `eq` `neq` `lt` `lte` `gt` `gte` | número |
| `date` | `eq` `neq` `lt` `lte` `gt` `gte` | `"AAAA-MM-DD"` |
| `single_choice` | `eq` `neq` | `value` de uma opção |
| `multiple_choice` | `includes` `excludes` | `value` de uma opção |
| `short_text`, `long_text`, `email` | `eq` `neq` (sem diferenciar maiúsculas e acentos) | texto |
| qualquer um dos acima | `answered` `not_answered` | ausente |

- `visibility` só em campos (`kind: input`), e o campo de origem precisa vir **antes** na lista. Isso torna ciclos impossíveis e combina com as etapas: só se depende de respostas já dadas.
- Origem não pode ser bloco nem `file`. No máximo **50** campos com visibilidade por formulário.
- **Avaliação em cadeia:** se o campo de origem está oculto, seu valor conta como não respondido.
- **No envio**, o back-end avalia pelo schema salvo e pelas respostas: campo oculto não é validado, não é obrigatório e **tem o valor descartado** (`null`), inclusive arquivos.
- O avaliador existe em PHP e deve existir em TypeScript; os casos ficam em `fixtures/validation-cases.json` → `visibility`.

## 4. Tema do formulário (versionado)

```ts
interface FormTheme {
  preset: 'classic' | 'minimal' | 'dark' | 'modern' | 'custom'
  colors: { primary: string; background: string; surface: string; text: string }   // "#RRGGBB"
  radius: 'none' | 'small' | 'medium' | 'large'
  font: 'inter' | 'system' | 'serif'
  button_style: 'solid' | 'outline'
  alignment: 'left' | 'center'
  cover: { asset_id: string; fit: 'cover' | 'contain'; overlay_opacity: number } | null   // overlay 0–80 (%)
  logo: { asset_id: string } | null
}
```

- Fica em `schema.theme` (versionado: o público só vê ao publicar). Ausente = padrão `classic`.
- Nas respostas da API, `cover` e `logo` ganham `url` (pública).

## 5. Imagens de capa e logo

| Método e rota | Corpo | Sucesso |
|---|---|---|
| `POST /me/forms/{id}/assets` | multipart: `file` (JPG, PNG ou WebP, até 5 MB), `kind` (`cover`/`logo`) | `201` `{ data: { id, kind, url, mime_type, size } }` |
| `DELETE /me/forms/{id}/assets/{assetId}` | — | `204`; `409` se o rascunho ou alguma versão publicada ainda usa a imagem |
| `GET /assets/{assetId}` | — | a imagem (pública, cache longo e imutável) |

- Cada imagem é **imutável**: trocar a capa = enviar outra e apontar o tema para o novo `asset_id`. A versão publicada continua usando a anterior.
- Uma imagem só é apagada quando nem o rascunho nem nenhuma versão a referenciam. Imagens enviadas e nunca usadas são apagadas após 24 h.
- No máximo 20 imagens por formulário.

## 6. Listagem de formulários

`FormSummary` ganha `submissions_count` (inteiro) e `last_submission_at` (ISO 8601 ou `null`).

## Fora desta versão

Pular para uma etapa específica; tipos Assinatura, Pagamento e Endereço; métricas de Resultados (taxa de conclusão, tempo médio, dispositivos, série temporal).
