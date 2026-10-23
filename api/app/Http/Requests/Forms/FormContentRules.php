<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Data\FormSettings;

/**
 * Regras compartilhadas entre criação e edição de formulário.
 */
final class FormContentRules
{
    public const TITLE = ['required', 'string', 'max:200'];

    public const DESCRIPTION = ['nullable', 'string', 'max:2000'];

    public const SLUG = ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];

    /** @var array<string, string> */
    public const ATTRIBUTES = [
        'title' => 'título',
        'description' => 'descrição',
        'slug' => 'endereço',
        'settings.notification_emails' => 'emails de notificação',
        'settings.notification_emails.*' => 'email de notificação',
        'settings.success_message' => 'mensagem de sucesso',
        'settings.max_submissions' => 'limite de respostas',
        'settings.opens_at' => 'data de abertura',
        'settings.closes_at' => 'data de fechamento',
        'settings.send_copy_to_respondent' => 'enviar cópia ao respondente',
        'settings.respondent_email_field' => 'campo de email do respondente',
        'settings.allow_respondent_edit' => 'permitir edição pelo respondente',
        'settings.retention_days' => 'prazo de retenção',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function settings(): array
    {
        return [
            'settings' => ['sometimes', 'array'],
            'settings.notification_emails' => ['sometimes', 'array', 'max:'.FormSettings::MAX_NOTIFICATION_EMAILS],
            'settings.notification_emails.*' => ['required', 'string', 'email:rfc', 'max:255', 'distinct:ignore_case'],
            'settings.success_message' => ['sometimes', 'nullable', 'string', 'max:'.FormSettings::SUCCESS_MESSAGE_MAX_LENGTH],
            'settings.max_submissions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.FormSettings::MAX_SUBMISSIONS_LIMIT],
            'settings.opens_at' => ['sometimes', 'nullable', 'date'],
            'settings.closes_at' => ['sometimes', 'nullable', 'date'],
            'settings.send_copy_to_respondent' => ['sometimes', 'boolean'],
            'settings.respondent_email_field' => ['sometimes', 'nullable', 'string', 'max:40'],
            'settings.allow_respondent_edit' => ['sometimes', 'boolean'],
            'settings.retention_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.FormSettings::MAX_RETENTION_DAYS],
        ];
    }
}
