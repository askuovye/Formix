<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Enums\FormAvailability;
use App\Exceptions\ApiException;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Regras de acesso ao link de edição do respondente (D15).
 */
final class RespondentEditGuard
{
    public const EXPIRED_MESSAGE = 'O prazo de 4 horas para editar esta resposta terminou.';

    public const FORM_CLOSED_MESSAGE = 'Este formulário não permite mais editar respostas.';

    public function __construct(private readonly EditTokens $editTokens) {}

    public function authorize(Form $form, Submission $submission, ?string $token): void
    {
        // Token ausente ou errado: 404, sem confirmar que a resposta existe.
        if ($token === null || ! $this->editTokens->matches($token, $submission->edit_token_hash)) {
            throw new NotFoundHttpException;
        }

        $availability = FormAvailability::of($form);

        // O dono pode desligar a edição, fechar o formulário ou o prazo pode ter terminado.
        // Atingir o limite de respostas não bloqueia: editar não cria resposta nova.
        if (! $form->settings->allowRespondentEdit || in_array($availability, [FormAvailability::Closed, FormAvailability::Ended], strict: true)) {
            throw ApiException::conflict(self::FORM_CLOSED_MESSAGE);
        }

        if (! $submission->isEditable()) {
            throw new ApiException(Response::HTTP_GONE, self::EXPIRED_MESSAGE);
        }
    }
}
