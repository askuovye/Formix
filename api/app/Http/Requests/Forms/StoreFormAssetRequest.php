<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Enums\FormAssetKind;
use App\Services\FormAssets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * O tipo real da imagem é conferido pelo conteúdo em FormAssets (aqui só tamanho e presença).
 */
final class StoreFormAssetRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.FormAssets::MAX_SIZE_KB],
            'kind' => ['required', Rule::enum(FormAssetKind::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => 'imagem', 'kind' => 'tipo de imagem'];
    }
}
