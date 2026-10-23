<?php

declare(strict_types=1);

use App\Jobs\Emails\SendNewSubmissionEmail;
use App\Jobs\Emails\SendRespondentCopyEmail;
use App\Jobs\Emails\SendSubmissionUpdatedEmail;
use App\Mail\NewSubmissionMail;
use App\Mail\RespondentCopyMail;
use App\Mail\SubmissionUpdatedMail;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $answers
 */
function respond(Form $form, array $answers = []): TestResponse
{
    return test()->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => [...validSubmissionAnswers(), ...$answers]])
        ->assertCreated();
}

it('enfileira os emails na fila de emails depois do envio', function () {
    Queue::fake();
    $form = publishedForm();

    respond($form);

    Queue::assertPushedOn('emails', SendNewSubmissionEmail::class);
    Queue::assertPushedOn('emails', SendRespondentCopyEmail::class);
});

it('criptografa na fila o job que leva o token de edição', function () {
    expect(new SendRespondentCopyEmail('id', 'token'))->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('avisa o dono com as respostas e marca como notificado', function () {
    Mail::fake();
    $form = publishedForm();

    respond($form);

    Mail::assertSent(NewSubmissionMail::class, function (NewSubmissionMail $mail) use ($form): bool {
        return $mail->hasTo($form->user->email)
            && $mail->formTitle === $form->title
            && $mail->rows[0] === ['field_id' => 'nome', 'label' => 'Nome completo', 'value' => 'Maria Souza', 'multiline' => false, 'answered' => true]
            && str_contains($mail->dashboardUrl, "/painel/formularios/{$form->id}/respostas/");
    });

    expect(Submission::query()->sole()->owner_notified_at)->not->toBeNull();
});

it('usa os emails de notificação configurados no lugar do dono', function () {
    Mail::fake();
    $form = publishedForm(['notification_emails' => ['equipe@example.com', 'gestao@example.com']]);

    respond($form);

    Mail::assertSent(NewSubmissionMail::class, fn (NewSubmissionMail $mail): bool => $mail->hasTo('equipe@example.com')
        && $mail->hasTo('gestao@example.com')
        && ! $mail->hasTo($form->user->email));
});

it('não reenvia quando o job roda de novo (retentativa)', function () {
    Mail::fake();
    respond(publishedForm());

    (new SendNewSubmissionEmail(Submission::query()->sole()->id))->handle();

    Mail::assertSentCount(1);
});

it('não envia nada se a resposta ou o formulário foram excluídos antes do job rodar', function (string $what) {
    Queue::fake();
    $form = publishedForm();
    respond($form);
    $submission = Submission::query()->sole();
    Mail::fake();

    $what === 'resposta' ? $submission->delete() : $form->delete();

    (new SendNewSubmissionEmail($submission->id))->handle();
    (new SendRespondentCopyEmail($submission->id, null))->handle();

    Mail::assertNothingSent();
})->with(['resposta', 'formulário']);

it('envia a cópia ao respondente com o link de edição', function () {
    Mail::fake();
    $form = publishedForm(['send_copy_to_respondent' => true, 'respondent_email_field' => 'email', 'allow_respondent_edit' => true]);

    $receipt = respond($form, ['email' => 'respondente@example.com'])->json('data');

    Mail::assertSent(RespondentCopyMail::class, fn (RespondentCopyMail $mail): bool => $mail->hasTo('respondente@example.com')
        && $mail->editUrl === $receipt['edit_url']
        && $mail->editableUntil !== null);

    expect(Submission::query()->sole()->respondent_notified_at)->not->toBeNull();
});

it('envia a cópia sem link quando a edição não está ativa', function () {
    Mail::fake();
    respond(publishedForm(['send_copy_to_respondent' => true, 'respondent_email_field' => 'email']));

    Mail::assertSent(RespondentCopyMail::class, fn (RespondentCopyMail $mail): bool => $mail->editUrl === null);
});

it('não envia cópia quando o dono não ativou', function () {
    Mail::fake();
    respond(publishedForm());

    Mail::assertNotSent(RespondentCopyMail::class);
    expect(Submission::query()->sole()->respondent_notified_at)->not->toBeNull();
});

it('limita as cópias para o mesmo endereço, contra uso do formulário para spam', function () {
    Mail::fake();
    $form = publishedForm(['send_copy_to_respondent' => true, 'respondent_email_field' => 'email']);

    $attempts = SendRespondentCopyEmail::MAX_COPIES_PER_HOUR + 2;

    for ($i = 0; $i < $attempts; $i++) {
        respond($form, ['email' => $i % 2 === 0 ? 'Vitima@Example.com' : 'vitima@example.com']);
    }

    // Todas as respostas são gravadas e avisadas ao dono; só as cópias param no limite.
    Mail::assertSent(NewSubmissionMail::class, $attempts);
    Mail::assertSent(RespondentCopyMail::class, SendRespondentCopyEmail::MAX_COPIES_PER_HOUR);
});

it('avisa o dono quando o respondente edita, uma vez por edição', function () {
    Mail::fake();
    $form = publishedForm(['allow_respondent_edit' => true]);
    $receipt = respond($form)->json('data');
    $token = explode('#token=', $receipt['edit_url'])[1];

    $this->withHeaders(['X-Edit-Token' => $token])
        ->putJson("/api/v1/forms/{$form->slug}/submissions/{$receipt['id']}", ['answers' => [...validSubmissionAnswers(), 'nome' => 'Maria Lima']])
        ->assertOk();

    Mail::assertSent(SubmissionUpdatedMail::class, fn (SubmissionUpdatedMail $mail): bool => $mail->hasTo($form->user->email)
        && $mail->rows[0]['value'] === 'Maria Lima');

    $submission = Submission::query()->sole();
    (new SendSubmissionUpdatedEmail($submission->id, $submission->edited_at->toIso8601String()))->handle();

    Mail::assertSent(SubmissionUpdatedMail::class, 1);
});

it('escapa o conteúdo do respondente no email, sem virar HTML nem link', function () {
    $mail = new NewSubmissionMail(
        formTitle: 'Contato',
        rows: [
            ['label' => 'Nome', 'value' => '<script>alert("x")</script>', 'multiline' => false, 'answered' => true],
            ['label' => 'Mensagem', 'value' => "[Clique aqui](http://golpe.test)\n<img src=x onerror=alert(1)>", 'multiline' => true, 'answered' => true],
        ],
        fileGroups: [],
        receivedAt: '24/10/2026 às 10:00',
        dashboardUrl: 'http://localhost:5173/painel/formularios/1/respostas/2',
        settingsUrl: 'http://localhost:5173/painel/formularios/1/configuracoes',
    );

    $html = $mail->render();

    expect($html)
        ->toContain('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->toContain('[Clique aqui](http://golpe.test)')
        ->not->toContain('<script>')
        ->not->toContain('href="http://golpe.test"')
        ->not->toContain('<img src=x');
});

it('mantém o texto original na versão texto puro do email', function () {
    $mail = new NewSubmissionMail('Contato', [['label' => 'Empresa', 'value' => 'Silva & Filhos', 'multiline' => false, 'answered' => true]], [], '24/10/2026 às 10:00', 'http://x.test/a', 'http://x.test/b');

    expect(view('mail.submissions.new-text', $mail->buildViewData())->render())
        ->toContain('Silva & Filhos')
        ->not->toContain('&amp;');
});

it('não deixa o título do formulário quebrar o cabeçalho do email', function () {
    $mail = new NewSubmissionMail("Contato\r\nBcc: intruso@example.com", [], [], '', 'http://x.test/a', 'http://x.test/b');

    expect($mail->envelope()->subject)->toBe('Nova resposta: Contato Bcc: intruso@example.com')
        ->not->toContain("\n");
});

it('registra falha definitiva sem dados pessoais', function () {
    Log::spy();

    (new SendRespondentCopyEmail('01jbsubmission0000000000000', 'token-secreto'))->failed(new RuntimeException('smtp caiu para respondente@example.com'));

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        return ! str_contains(json_encode($context), '@')
            && ! str_contains(json_encode($context), 'token-secreto')
            && $context['submission_id'] === '01jbsubmission0000000000000';
    });
});
