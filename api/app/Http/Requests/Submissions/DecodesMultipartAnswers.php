<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

/**
 * Com anexos, o envio é `multipart/form-data`: as respostas vão no campo
 * `answers` como texto JSON (evita as limitações de aninhar dados no FormData)
 * e os arquivos em `files[<id do campo>][]`. Sem anexos, JSON puro.
 */
trait DecodesMultipartAnswers
{
    protected function prepareForValidation(): void
    {
        $answers = $this->input('answers');

        if (is_string($answers)) {
            $decoded = json_decode($answers, true);
            // JSON inválido continua texto e falha na regra `array`.
            $this->merge(['answers' => is_array($decoded) ? $decoded : $answers]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function envelopeRules(): array
    {
        return [
            'answers' => ['present', 'array', 'max:'.StoreSubmissionRequest::MAX_ANSWER_KEYS],
            'files' => ['sometimes', 'array', 'max:'.StoreSubmissionRequest::MAX_ANSWER_KEYS],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        /** @var array<string, mixed> $answers */
        $answers = (array) $this->input('answers', []);

        return $answers;
    }

    /**
     * Arquivos por id de campo (`files[<id>][]`): cada valor é um arquivo ou uma lista.
     *
     * @return array<string, mixed>
     */
    public function uploadedFiles(): array
    {
        $files = $this->allFiles()['files'] ?? [];
        $byField = [];

        // Ids de campo sempre começam com letra: chave numérica nunca é um campo.
        foreach (is_array($files) ? $files : [] as $fieldId => $value) {
            if (is_string($fieldId)) {
                $byField[$fieldId] = $value;
            }
        }

        return $byField;
    }
}
