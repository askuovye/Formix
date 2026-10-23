<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edição pelo respondente. Com anexos, o PHP só lê multipart em POST: a SPA
 * envia POST com `_method=PUT` (method spoofing do Laravel).
 */
final class UpdateSubmissionRequest extends FormRequest
{
    use DecodesMultipartAnswers;

    public const EDIT_TOKEN_HEADER = 'X-Edit-Token';

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->envelopeRules();
    }

    public function editToken(): ?string
    {
        return $this->header(self::EDIT_TOKEN_HEADER);
    }
}
