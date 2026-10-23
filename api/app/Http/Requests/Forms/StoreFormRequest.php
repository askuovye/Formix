<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFormRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => FormContentRules::TITLE,
            'description' => FormContentRules::DESCRIPTION,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return FormContentRules::ATTRIBUTES;
    }
}
