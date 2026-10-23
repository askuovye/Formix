<?php

declare(strict_types=1);

namespace App\Validation;

use App\Data\AcceptedFile;
use Illuminate\Validation\ValidationException;

/**
 * Valida respostas de texto e arquivos juntos e devolve todos os erros de uma
 * vez: o respondente não deve corrigir um campo, enviar e só então descobrir outro.
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
     * @return array{answers: array<string, mixed>, files: array<string, list<AcceptedFile>>}
     *
     * @throws ValidationException
     */
    public function validate(array $schema, array $answers, array $files, array $existingFileCounts = []): array
    {
        $errors = [];
        $validatedAnswers = [];

        try {
            $validatedAnswers = $this->answers->validate($schema, $answers);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        $fileResult = $this->files->validate($schema, $files, $existingFileCounts);
        $errors = [...$errors, ...$fileResult->errors];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['answers' => $validatedAnswers, 'files' => $fileResult->accepted];
    }
}
