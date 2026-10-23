<?php

declare(strict_types=1);

namespace App\Data;

use App\Casts\FormSettingsCast;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Configurações operacionais do formulário. Não fazem parte da versão
 * publicada: mudanças valem imediatamente, inclusive em formulário no ar.
 */
final readonly class FormSettings implements Castable
{
    public const MAX_NOTIFICATION_EMAILS = 5;

    public const SUCCESS_MESSAGE_MAX_LENGTH = 500;

    public const MAX_SUBMISSIONS_LIMIT = 1_000_000;

    public const MAX_RETENTION_DAYS = 3650;

    /**
     * @param  list<string>  $notificationEmails  vazio = email do dono
     */
    public function __construct(
        public array $notificationEmails = [],
        public ?string $successMessage = null,
        public ?int $maxSubmissions = null,
        public ?CarbonImmutable $opensAt = null,
        public ?CarbonImmutable $closesAt = null,
        public bool $sendCopyToRespondent = false,
        public ?string $respondentEmailField = null,
        public bool $allowRespondentEdit = false,
        public ?int $retentionDays = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  chaves em snake_case (formato da API e do banco)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            notificationEmails: array_values(array_map('strval', (array) ($data['notification_emails'] ?? []))),
            successMessage: self::nullableString($data['success_message'] ?? null),
            maxSubmissions: self::nullableInt($data['max_submissions'] ?? null),
            opensAt: self::nullableDate($data['opens_at'] ?? null),
            closesAt: self::nullableDate($data['closes_at'] ?? null),
            sendCopyToRespondent: (bool) ($data['send_copy_to_respondent'] ?? false),
            respondentEmailField: self::nullableString($data['respondent_email_field'] ?? null),
            allowRespondentEdit: (bool) ($data['allow_respondent_edit'] ?? false),
            retentionDays: self::nullableInt($data['retention_days'] ?? null),
        );
    }

    /**
     * Aplica uma atualização parcial: só as chaves enviadas mudam.
     *
     * @param  array<string, mixed>  $changes
     */
    public function merge(array $changes): self
    {
        return self::fromArray([...$this->toArray(), ...$changes]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'notification_emails' => $this->notificationEmails,
            'success_message' => $this->successMessage,
            'max_submissions' => $this->maxSubmissions,
            'opens_at' => $this->opensAt?->utc()->toIso8601String(),
            'closes_at' => $this->closesAt?->utc()->toIso8601String(),
            'send_copy_to_respondent' => $this->sendCopyToRespondent,
            'respondent_email_field' => $this->respondentEmailField,
            'allow_respondent_edit' => $this->allowRespondentEdit,
            'retention_days' => $this->retentionDays,
        ];
    }

    /**
     * @param  array<mixed>  $arguments
     * @return CastsAttributes<self, self>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new FormSettingsCast;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableDate(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
