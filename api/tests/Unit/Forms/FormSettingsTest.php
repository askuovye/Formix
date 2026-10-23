<?php

declare(strict_types=1);

use App\Data\FormSettings;

it('usa padrões seguros quando não há configuração', function () {
    $settings = FormSettings::fromArray([]);

    expect($settings->notificationEmails)->toBe([])
        ->and($settings->maxSubmissions)->toBeNull()
        ->and($settings->sendCopyToRespondent)->toBeFalse()
        ->and($settings->allowRespondentEdit)->toBeFalse();
});

it('converte de e para array sem perder informação', function () {
    $data = [
        'notification_emails' => ['a@example.com'],
        'success_message' => 'Obrigado!',
        'max_submissions' => 10,
        'opens_at' => '2026-11-01T12:00:00+00:00',
        'closes_at' => '2026-11-30T12:00:00+00:00',
        'send_copy_to_respondent' => true,
        'respondent_email_field' => 'email',
        'allow_respondent_edit' => true,
        'retention_days' => 30,
    ];

    expect(FormSettings::fromArray($data)->toArray())->toBe($data);
});

it('normaliza datas para UTC', function () {
    expect(FormSettings::fromArray(['opens_at' => '2026-11-01T09:00:00-03:00'])->toArray()['opens_at'])
        ->toBe('2026-11-01T12:00:00+00:00');
});

it('trata texto vazio como ausente', function () {
    $settings = FormSettings::fromArray(['success_message' => '   ', 'respondent_email_field' => '']);

    expect($settings->successMessage)->toBeNull()
        ->and($settings->respondentEmailField)->toBeNull();
});

it('mescla só as chaves enviadas', function () {
    $merged = (new FormSettings(successMessage: 'Valeu', maxSubmissions: 5))->merge(['max_submissions' => null]);

    expect($merged->successMessage)->toBe('Valeu')
        ->and($merged->maxSubmissions)->toBeNull();
});

it('ignora chaves desconhecidas', function () {
    expect(FormSettings::fromArray(['webhook' => 'http://evil.test'])->toArray())->not->toHaveKey('webhook');
});
