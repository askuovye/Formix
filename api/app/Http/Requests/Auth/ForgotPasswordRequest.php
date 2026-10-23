<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    use NormalizesEmail;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['email' => $this->emailRules()];
    }
}
