<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    use NormalizesEmail {
        prepareForValidation as normalizeEmail;
    }

    private const NAME_MAX_LENGTH = 120;

    /**
     * @return array<string, list<string|ValidationRule|Password>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX_LENGTH],
            'email' => $this->emailRules(),
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            // Um checkbox único cobre os dois documentos; o aceite de cada um é registrado.
            'terms_accepted' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_accepted.accepted' => 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmail();

        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }
}
