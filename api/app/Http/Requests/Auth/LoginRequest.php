<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    use NormalizesEmail;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => $this->emailRules(),
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
