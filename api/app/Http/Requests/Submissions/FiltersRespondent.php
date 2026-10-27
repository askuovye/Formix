<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use App\Services\Submissions\RespondentEmail;

/**
 * Filtro `email`: respostas de uma pessoa (comparação exata, sem maiúsculas).
 */
trait FiltersRespondent
{
    /**
     * @return array<string, list<string>>
     */
    protected function respondentRules(): array
    {
        return ['email' => ['sometimes', 'nullable', 'string', 'email', 'max:255']];
    }

    public function respondentEmail(): ?string
    {
        return $this->filled('email') ? RespondentEmail::normalize($this->string('email')->value()) : null;
    }
}
