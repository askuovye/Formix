<?php

declare(strict_types=1);

namespace App\Services\FormSchema;

use App\Data\FormSettings;
use App\Enums\FieldType;
use App\Models\Form;
use Illuminate\Validation\ValidationException;

/**
 * Regras que cruzam configurações e schema (o Form Request valida cada parte isolada).
 */
final class FormConsistency
{
    public const NO_FIELDS_MESSAGE = 'Adicione pelo menos um campo antes de publicar.';

    public const RESPONDENT_EMAIL_FIELD_MESSAGE = 'Escolha um campo do tipo email deste formulário para receber a cópia da resposta.';

    public const CLOSES_BEFORE_OPENS_MESSAGE = 'O fechamento deve ser depois da abertura.';

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     */
    public function assertSettingsMatchSchema(FormSettings $settings, array $schema): void
    {
        if ($settings->opensAt !== null && $settings->closesAt !== null && $settings->closesAt->lte($settings->opensAt)) {
            throw ValidationException::withMessages(['settings.closes_at' => self::CLOSES_BEFORE_OPENS_MESSAGE]);
        }

        if ($settings->sendCopyToRespondent && ! $this->isEmailField($schema, $settings->respondentEmailField)) {
            throw ValidationException::withMessages(['settings.respondent_email_field' => self::RESPONDENT_EMAIL_FIELD_MESSAGE]);
        }
    }

    public function assertPublishable(Form $form): void
    {
        // Só blocos (títulos, textos) não formam um formulário: precisa de ao menos um campo.
        if (SchemaItems::inputs($form->schema) === []) {
            throw ValidationException::withMessages(['schema' => self::NO_FIELDS_MESSAGE]);
        }

        $this->assertSettingsMatchSchema($form->settings, $form->schema);
    }

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     */
    private function isEmailField(array $schema, ?string $fieldId): bool
    {
        foreach (SchemaItems::inputs($schema) as $field) {
            if ($field['id'] === $fieldId) {
                return $field['type'] === FieldType::Email->value;
            }
        }

        return false;
    }
}
