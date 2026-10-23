<?php

declare(strict_types=1);

namespace App\Validation;

use App\Data\AcceptedFile;
use Illuminate\Validation\ValidationException;

/**
 * Valida respostas de texto e arquivos juntos e devolve todos os erros de uma
 * vez: o respondente não deve corrigir um campo, enviar e só então descobrir outro.
 *
 * A visibilidade (lógica condicional) é calculada uma vez, pelo schema salvo e
 * pelas respostas, e vale para os dois lados: campo oculto não é validado e
 * nada dele é gravado, nem arquivo (D32).
 */
final class ResponseValidator
{
    public function __construct(
        private readonly FormSubmissionValidator $answers,
        private readonly FileAnswersValidator $files,
    ) {}

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $files
     * @param  array<string, int>  $existingFileCounts
     * @return array{answers: array<string, mixed>, files: array<string, list<AcceptedFile>>, hidden: list<string>}
     *
     * @throws ValidationException
     */
    public function validate(array $schema, array $answers, array $files, array $existingFileCounts = []): array
    {
        $hidden = $this->answers->hiddenFieldIds($schema, $answers);
        $errors = [];
        $validatedAnswers = [];

        try {
            $validatedAnswers = $this->answers->validateVisible($schema, $answers)['answers'];
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        $fileResult = $this->files->validate($schema, $files, $existingFileCounts, $hidden);
        $errors = [...$errors, ...$fileResult->errors];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['answers' => $validatedAnswers, 'files' => $fileResult->accepted, 'hidden' => $hidden];
    }
}
