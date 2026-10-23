# Fixtures de validação

`validation-cases.json` é o **contrato** do motor de validação: o backend (Pest, `api/tests/Unit/Validation/ValidationFixturesTest.php`) e o espelho do front (Vitest) devem passar por **todos** os casos. Mudou uma regra? Atualize os casos aqui primeiro.

## Seções

### `normalization`

```json
{ "note": "…", "input": "  a  b ", "multiline": false, "output": "a b" }
```

`normalize(input, multiline) === output`. `multiline: true` é usado em `long_text`. A ordem dos passos está documentada em `api/app/Validation/InputNormalizer.php`.

### `rules`

```json
"no_repeated_chars": [
  { "params": { "threshold": 0.8 }, "value": "aaaaa", "valid": false, "note": "opcional", "multiline": false }
]
```

Para cada caso: normalize `value` (com `multiline`, padrão `false`) e aplique a regra com `params`. O resultado deve ser `valid`. Os casos nunca têm valor vazio (vazio é tratado por `required`, antes das regras). Toda regra do catálogo tem ao menos um caso válido e um inválido (verificado por teste).

### `fields`

```json
{ "note": "…", "field": { "type": "number", "required": true, "options": ["a", "b"] }, "value": "3,5", "valid": true, "normalized": "3.5" }
```

Validação pelo **tipo do campo** (email, número, data, escolha), passando pelo validador completo. `options` é a lista de `value` das opções. Quando `valid`, a resposta normalizada deve ser igual a `normalized` (`null` = não respondido).

### `visibility`

```json
{ "note": "…", "schema": { "fields": [ { "id": "nota", "type": "rating" },
    { "id": "motivo", "type": "long_text", "visibility": { "action": "show", "when": { "field": "nota", "operator": "lte", "value": 2 } } } ] },
  "answers": { "nota": 4 }, "hidden": ["motivo"] }
```

Avaliador da lógica condicional: `hiddenFieldIds(schema, answers)` deve devolver exatamente `hidden` (na ordem do schema). Os itens trazem só o necessário (`id`, `type`, `kind?`, `visibility?`). Regras:

- percorre os campos (`kind` ausente = `input`) na ordem; blocos são ignorados;
- `show` = visível só se a condição for verdadeira; `hide` = oculto se for verdadeira;
- origem oculta conta como **não respondida**; não respondido = `null`, texto só com espaços ou lista vazia;
- `answered`/`not_answered` olham só a presença; qualquer outra comparação com origem não respondida é **falsa** (inclusive `neq` e `excludes`);
- número aceita texto com vírgula (`"2,5"`); valor que não é do tipo esperado torna a condição falsa;
- texto compara pela forma dobrada (sem maiúsculas e acentos) do texto normalizado; data compara `AAAA-MM-DD`.

## Definições que o espelho precisa seguir

| Conceito | Definição |
|---|---|
| Tamanho | caracteres Unicode (code points) do texto normalizado. Em JS: `[...texto].length` |
| Forma dobrada | minúsculas + NFD sem marcas combinantes (`\p{Mn}`) |
| Forma compacta | forma dobrada só com letras e dígitos (`[\p{L}\p{N}]`) |
| Palavra | trecho entre espaços com ao menos uma letra ou dígito |
| Número | `^-?\d{1,15}(\.\d{1,6})?$`, aceitando vírgula como separador decimal na entrada; normalizado como texto |
| Data | `AAAA-MM-DD`, data existente |
| Estrelas / escala | inteiro no intervalo (`1..max` / `min..max`); texto só com dígitos vira inteiro |

Nenhum dado real: CPF e CNPJ são exemplos públicos de documentação.
