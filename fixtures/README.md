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

## Definições que o espelho precisa seguir

| Conceito | Definição |
|---|---|
| Tamanho | caracteres Unicode (code points) do texto normalizado. Em JS: `[...texto].length` |
| Forma dobrada | minúsculas + NFD sem marcas combinantes (`\p{Mn}`) |
| Forma compacta | forma dobrada só com letras e dígitos (`[\p{L}\p{N}]`) |
| Palavra | trecho entre espaços com ao menos uma letra ou dígito |
| Número | `^-?\d{1,15}(\.\d{1,6})?$`, aceitando vírgula como separador decimal na entrada; normalizado como texto |
| Data | `AAAA-MM-DD`, data existente |

Nenhum dado real: CPF e CNPJ são exemplos públicos de documentação.
