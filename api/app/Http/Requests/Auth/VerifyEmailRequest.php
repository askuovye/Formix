<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Actions\Auth\VerifyUserEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class VerifyEmailRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Assinatura relativa: o link é montado pela SPA, então só o caminho
     * e a query precisam conferir, não o domínio.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasValidRelativeSignature()) {
                    $validator->errors()->add('signature', VerifyUserEmail::INVALID_LINK_MESSAGE);
                }
            },
        ];
    }

    public function userId(): string
    {
        return (string) $this->route('id');
    }

    public function emailHash(): string
    {
        return (string) $this->route('hash');
    }
}
