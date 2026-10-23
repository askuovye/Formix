<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Models\Form;
use App\Services\FormSchema\FormSchemaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Atualização parcial: só as chaves enviadas são validadas e alteradas.
 */
final class UpdateFormRequest extends FormRequest
{
    // Teto do schema serializado: protege banco, cache e o renderer de payloads gigantes.
    public const MAX_SCHEMA_BYTES = 256 * 1024;

    public const SCHEMA_TOO_LARGE_MESSAGE = 'O formulário ficou grande demais. Remova campos, opções ou regras.';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Form $form */
        $form = $this->route('form');

        $rules = [
            'title' => ['sometimes', ...FormContentRules::TITLE],
            'description' => ['sometimes', ...FormContentRules::DESCRIPTION],
            // Unique sem filtrar excluídos: slug de formulário apagado continua reservado.
            'slug' => ['sometimes', ...FormContentRules::SLUG, Rule::unique('forms', 'slug')->ignore($form->id)],
            ...FormContentRules::settings(),
        ];

        if ($this->has('schema')) {
            $rules = [...$rules, ...app(FormSchemaRules::class)->rules($this->input('schema'))];
        }

        return $rules;
    }

    /**
     * Dados validados para a Action.
     *
     * O schema vem do input original (que passou inteiro pela validação): o
     * `validated()` do Laravel remonta listas a partir das regras por índice e
     * reordena os itens, e a ordem de campos, opções e regras é parte do
     * conteúdo. Chaves desconhecidas são descartadas pelo FormSchemaNormalizer.
     *
     * @return array<string, mixed>
     */
    public function formData(): array
    {
        $data = $this->validated();

        if ($this->has('schema')) {
            $data['schema'] = $this->input('schema');
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...FormContentRules::ATTRIBUTES, ...app(FormSchemaRules::class)->attributes()];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->has('schema') && strlen((string) json_encode($this->input('schema'))) > self::MAX_SCHEMA_BYTES) {
                    $validator->errors()->add('schema', self::SCHEMA_TOO_LARGE_MESSAGE);
                }
            },
        ];
    }
}
