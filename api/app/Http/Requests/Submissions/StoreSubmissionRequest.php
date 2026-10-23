<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use App\Data\SubmissionAttempt;
use App\Services\Submissions\CaptchaVerifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Só a "casca" do envio é validada aqui; respostas e arquivos são validados
 * pelo motor contra o schema publicado.
 */
final class StoreSubmissionRequest extends FormRequest
{
    use DecodesMultipartAnswers;

    // Campo invisível na página pública: gente não preenche, bot preenche.
    public const HONEYPOT_FIELD = 'website';

    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public const MAX_ANSWER_KEYS = 200;

    private const IDEMPOTENCY_KEY_PATTERN = '/^[A-Za-z0-9_\-:.]{8,128}$/';

    public const INVALID_IDEMPOTENCY_KEY_MESSAGE = 'O cabeçalho Idempotency-Key deve ter de 8 a 128 caracteres (letras, números, - _ : .).';

    public const CAPTCHA_FAILED_MESSAGE = 'Não foi possível confirmar que você não é um robô. Tente novamente.';

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...$this->envelopeRules(),
            self::HONEYPOT_FIELD => ['nullable'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $key = $this->header(self::IDEMPOTENCY_HEADER);

                if ($key !== null && preg_match(self::IDEMPOTENCY_KEY_PATTERN, $key) !== 1) {
                    $validator->errors()->add('idempotency_key', self::INVALID_IDEMPOTENCY_KEY_MESSAGE);
                }

                if (! app(CaptchaVerifier::class)->verify($this)) {
                    $validator->errors()->add('captcha', self::CAPTCHA_FAILED_MESSAGE);
                }
            },
        ];
    }

    public function attempt(): SubmissionAttempt
    {
        return new SubmissionAttempt(
            answers: $this->answers(),
            files: $this->uploadedFiles(),
            honeypotFilled: filled($this->input(self::HONEYPOT_FIELD)),
            idempotencyKey: $this->header(self::IDEMPOTENCY_HEADER),
            ip: $this->ip(),
            userAgent: $this->userAgent(),
        );
    }
}
