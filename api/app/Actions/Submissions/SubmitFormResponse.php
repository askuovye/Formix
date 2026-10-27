<?php

declare(strict_types=1);

namespace App\Actions\Submissions;

use App\Data\SubmissionAttempt;
use App\Data\SubmissionReceipt;
use App\Enums\FormAvailability;
use App\Events\SubmissionReceived;
use App\Exceptions\ApiException;
use App\Models\Form;
use App\Models\Submission;
use App\Services\FrontendUrlGenerator;
use App\Services\IpHasher;
use App\Services\Submissions\AttachmentStorage;
use App\Services\Submissions\EditTokens;
use App\Services\Submissions\PublishedFormVersions;
use App\Services\Submissions\RespondentEmail;
use App\Validation\ResponseValidator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recebe uma resposta pela rota pública. O schema usado é sempre o da versão
 * publicada no banco, nunca algo enviado pelo cliente (AGENTS.md §3).
 */
final class SubmitFormResponse
{
    public const DEFAULT_SUCCESS_MESSAGE = 'Resposta enviada. Obrigado!';

    public const EDIT_WINDOW_HOURS = 4;

    public const IN_PROGRESS_MESSAGE = 'Seu envio anterior ainda está sendo processado. Aguarde um instante e tente de novo.';

    private const USER_AGENT_MAX_LENGTH = 255;

    // Tempo em que um reenvio com a mesma Idempotency-Key devolve o mesmo recibo.
    private const RECEIPT_TTL_SECONDS = 86_400;

    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private readonly ResponseValidator $validator,
        private readonly AttachmentStorage $attachments,
        private readonly PublishedFormVersions $versions,
        private readonly EditTokens $editTokens,
        private readonly IpHasher $ipHasher,
        private readonly FrontendUrlGenerator $urls,
        private readonly Cache $cache,
        private readonly Encrypter $encrypter,
    ) {}

    public function __invoke(Form $form, SubmissionAttempt $attempt): SubmissionReceipt
    {
        // Bot preencheu o campo invisível: responde igual a um envio real, sem gravar nada,
        // para que ele não perceba que foi barrado.
        if ($attempt->honeypotFilled) {
            return $this->fakeReceipt($form);
        }

        if ($attempt->idempotencyKey === null) {
            return $this->submit($form, $attempt, null);
        }

        $keyHash = hash('sha256', $form->id.'|'.$attempt->idempotencyKey);

        try {
            // Lock atômico do store de cache (database/redis): dois envios simultâneos com a
            // mesma chave esperam um pelo outro em vez de competirem.
            return CacheFacade::lock("submission-lock:{$keyHash}", self::LOCK_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                fn (): SubmissionReceipt => $this->replay($form, $keyHash) ?? $this->submit($form, $attempt, $keyHash),
            );
        } catch (LockTimeoutException) {
            throw ApiException::conflict(self::IN_PROGRESS_MESSAGE);
        }
    }

    private function submit(Form $form, SubmissionAttempt $attempt, ?string $keyHash): SubmissionReceipt
    {
        $this->assertOpen(FormAvailability::of($form));

        $version = $this->versions->get($form);
        ['answers' => $answers, 'files' => $files] = $this->validator->validate($version['schema'], $attempt->answers, $attempt->files);

        $editToken = $form->settings->allowRespondentEdit ? $this->editTokens->generate() : null;
        $storedFiles = [];

        try {
            $submission = DB::transaction(function () use ($form, $attempt, $keyHash, $version, $answers, $files, $editToken, &$storedFiles): Submission {
                // Trava o formulário: limite de respostas e status conferidos sem corrida.
                $locked = Form::query()->lockForUpdate()->findOrFail($form->id);
                $this->assertOpen(FormAvailability::of($locked));

                $submission = new Submission;
                $submission->forceFill([
                    'form_id' => $locked->id,
                    'schema_version' => $version['version'],
                    'data' => $answers,
                    'respondent_email' => RespondentEmail::from($version['schema'], $answers),
                    'ip_hash' => $this->ipHasher->hash($attempt->ip),
                    'user_agent' => $attempt->userAgent !== null ? mb_substr($attempt->userAgent, 0, self::USER_AGENT_MAX_LENGTH) : null,
                    'idempotency_key' => $keyHash,
                    'edit_token_hash' => $editToken !== null ? $this->editTokens->hash($editToken) : null,
                    'editable_until' => $editToken !== null ? now()->addHours(self::EDIT_WINDOW_HOURS) : null,
                ])->save();

                $locked->increment('submissions_count');

                // Por último: o que mais pode falhar (disco) acontece depois de tudo que é só banco.
                $storedFiles = $this->attachments->attach($submission, $files);

                return $submission;
            });
        } catch (Throwable $exception) {
            // Rollback desfaz as linhas de mídia, não os arquivos no disco.
            $this->attachments->deleteFiles($storedFiles);

            throw $exception;
        }

        event(new SubmissionReceived($submission, $editToken));

        $receipt = $this->receipt($form, $submission->id, $editToken, $submission->editable_until?->toImmutable());

        if ($keyHash !== null) {
            $this->cache->put("submission-receipt:{$keyHash}", $this->encrypter->encrypt($receipt->toArray()), self::RECEIPT_TTL_SECONDS);
        }

        return $receipt;
    }

    /**
     * Mesmo envio de novo (duplo clique, retry de rede): devolve o recibo original.
     * O recibo fica criptografado no cache porque contém o link de edição.
     */
    private function replay(Form $form, string $keyHash): ?SubmissionReceipt
    {
        $cached = $this->cache->get("submission-receipt:{$keyHash}");

        if (is_string($cached)) {
            return SubmissionReceipt::fromArray((array) $this->encrypter->decrypt($cached));
        }

        // Cache perdido: a resposta existe (garantido pelo índice único), mas o token
        // de edição em texto puro não é recuperável. Devolve o recibo sem o link.
        $existing = Submission::query()
            ->where('form_id', $form->id)
            ->where('idempotency_key', $keyHash)
            ->first();

        return $existing !== null ? $this->receipt($form, $existing->id, null, null) : null;
    }

    private function receipt(Form $form, string $submissionId, ?string $editToken, ?CarbonImmutable $editableUntil): SubmissionReceipt
    {
        return new SubmissionReceipt(
            id: $submissionId,
            successMessage: $form->settings->successMessage ?? self::DEFAULT_SUCCESS_MESSAGE,
            editUrl: $editToken !== null ? $this->urls->submissionEdit($form->slug, $submissionId, $editToken) : null,
            editableUntil: $editableUntil?->utc()->toIso8601String(),
        );
    }

    private function fakeReceipt(Form $form): SubmissionReceipt
    {
        $fakeToken = $form->settings->allowRespondentEdit ? $this->editTokens->generate() : null;

        return $this->receipt(
            $form,
            Str::lower((string) Str::ulid()),
            $fakeToken,
            $fakeToken !== null ? CarbonImmutable::now()->addHours(self::EDIT_WINDOW_HOURS) : null,
        );
    }

    private function assertOpen(FormAvailability $availability): void
    {
        if (! $availability->acceptsSubmissions()) {
            throw ApiException::conflict((string) $availability->message());
        }
    }
}
