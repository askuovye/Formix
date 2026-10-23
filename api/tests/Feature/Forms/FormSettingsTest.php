<?php

declare(strict_types=1);

use App\Data\FormSettings;
use App\Models\Form;
use App\Services\FormSchema\FormConsistency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $settings
 */
function saveSettings(Form $form, array $settings): TestResponse
{
    return test()->actingAs($form->user)->patchJson("/api/v1/me/forms/{$form->id}", ['settings' => $settings]);
}

it('salva as configurações', function () {
    $form = Form::factory()->withFields()->create();

    saveSettings($form, [
        'notification_emails' => ['equipe@example.com', 'gestao@example.com'],
        'success_message' => 'Obrigado!',
        'max_submissions' => 500,
        'opens_at' => '2026-11-01T09:00:00-03:00',
        'closes_at' => '2026-11-30T18:00:00-03:00',
        'send_copy_to_respondent' => true,
        'respondent_email_field' => 'email',
        'allow_respondent_edit' => true,
        'retention_days' => 180,
    ])
        ->assertOk()
        ->assertJsonPath('data.settings', [
            'notification_emails' => ['equipe@example.com', 'gestao@example.com'],
            'success_message' => 'Obrigado!',
            'max_submissions' => 500,
            'opens_at' => '2026-11-01T12:00:00+00:00',
            'closes_at' => '2026-11-30T21:00:00+00:00',
            'send_copy_to_respondent' => true,
            'respondent_email_field' => 'email',
            'allow_respondent_edit' => true,
            'retention_days' => 180,
        ]);
});

it('atualiza só as chaves enviadas', function () {
    $form = Form::factory()->withFields()->create(['settings' => new FormSettings(successMessage: 'Valeu!', maxSubmissions: 50)]);

    saveSettings($form, ['max_submissions' => 80])
        ->assertOk()
        ->assertJsonPath('data.settings.max_submissions', 80)
        ->assertJsonPath('data.settings.success_message', 'Valeu!');
});

it('exige que o fechamento seja depois da abertura', function () {
    $form = Form::factory()->withFields()->create();

    saveSettings($form, ['opens_at' => '2026-11-10T10:00:00Z', 'closes_at' => '2026-11-10T09:00:00Z'])
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors): bool => $errors['settings.closes_at'][0] === FormConsistency::CLOSES_BEFORE_OPENS_MESSAGE);
});

it('exige um campo de email do formulário para enviar cópia ao respondente', function (?string $field) {
    $form = Form::factory()->withFields()->create();

    saveSettings($form, ['send_copy_to_respondent' => true, 'respondent_email_field' => $field])
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors): bool => $errors['settings.respondent_email_field'][0] === FormConsistency::RESPONDENT_EMAIL_FIELD_MESSAGE);
})->with([
    'sem campo' => null,
    'campo inexistente' => 'telefone',
    'campo que não é email' => 'nome',
]);

it('confere a cópia ao respondente de novo ao publicar', function () {
    $form = Form::factory()->withFields()->create([
        'settings' => new FormSettings(sendCopyToRespondent: true, respondentEmailField: 'email'),
    ]);
    // O dono remove o campo de email do rascunho depois de configurar a cópia.
    $form->forceFill(['schema' => ['fields' => [$form->schema['fields'][0]]]])->save();

    $this->actingAs($form->user)
        ->postJson("/api/v1/me/forms/{$form->id}/publish")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.respondent_email_field']);
});

it('valida os limites das configurações', function (array $settings, string $errorKey) {
    $form = Form::factory()->withFields()->create();

    saveSettings($form, $settings)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorKey]);
})->with([
    'emails demais' => [['notification_emails' => ['a@x.com', 'b@x.com', 'c@x.com', 'd@x.com', 'e@x.com', 'f@x.com']], 'settings.notification_emails'],
    'email inválido' => [['notification_emails' => ['nao-email']], 'settings.notification_emails.0'],
    'email repetido' => [['notification_emails' => ['a@x.com', 'A@x.com']], 'settings.notification_emails.1'],
    'limite de respostas zero' => [['max_submissions' => 0], 'settings.max_submissions'],
    'mensagem longa demais' => [['success_message' => str_repeat('a', 501)], 'settings.success_message'],
    'data inválida' => [['opens_at' => 'amanhã'], 'settings.opens_at'],
    'retenção longa demais' => [['retention_days' => 4000], 'settings.retention_days'],
]);
