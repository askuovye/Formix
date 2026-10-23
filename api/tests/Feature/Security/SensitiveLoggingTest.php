<?php

declare(strict_types=1);

use App\Models\Submission;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('registra erros de banco sem os valores das consultas', function () {
    Log::spy();

    $exception = new QueryException(
        'mariadb',
        'insert into `submissions` (`data`, `ip_hash`) values (?, ?)',
        ['{"email":"maria@example.com","cpf":"123.456.789-09"}', 'hash-secreto'],
        new PDOException('SQLSTATE[23000]: Integrity constraint violation'),
    );

    app(ExceptionHandler::class)->report($exception);

    Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context): bool {
        $logged = $message.json_encode($context);

        return $context['sql'] === 'insert into `submissions` (`data`, `ip_hash`) values (?, ?)'
            && ! str_contains($logged, 'maria@example.com')
            && ! str_contains($logged, '123.456.789-09')
            && ! str_contains($logged, 'hash-secreto');
    });
});

it('não registra o conteúdo das respostas quando o banco falha durante um envio', function () {
    Log::spy();
    $form = publishedForm();

    // Simula o banco recusando a gravação da resposta.
    Submission::creating(fn () => throw new QueryException(
        'mariadb',
        'insert into `submissions` (`data`) values (?)',
        [json_encode(validSubmissionAnswers())],
        new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'),
    ));

    $this->postJson("/api/v1/forms/{$form->slug}/submissions", ['answers' => validSubmissionAnswers()])
        ->assertStatus(500);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => ! str_contains($message.json_encode($context), 'maria@example.com'));
    Log::shouldNotHaveReceived('error', [Mockery::on(fn ($message): bool => is_string($message) && str_contains($message, 'maria@example.com')), Mockery::any()]);
});
