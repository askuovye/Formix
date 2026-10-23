<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use Illuminate\Foundation\Http\FormRequest;

final class StoreExportRequest extends FormRequest
{
    use ParsesPeriod;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->periodRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => 'data inicial', 'to' => 'data final'];
    }
}
