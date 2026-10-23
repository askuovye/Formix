<?php

declare(strict_types=1);

namespace App\Data;

/**
 * O que o respondente recebe depois de enviar. Também é o que fica em cache
 * (criptografado) para repetir a mesma resposta em reenvios com a mesma
 * Idempotency-Key.
 */
final readonly class SubmissionReceipt
{
    public function __construct(
        public string $id,
        public string $successMessage,
        public ?string $editUrl = null,
        public ?string $editableUntil = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            successMessage: (string) $data['success_message'],
            editUrl: isset($data['edit_url']) ? (string) $data['edit_url'] : null,
            editableUntil: isset($data['editable_until']) ? (string) $data['editable_until'] : null,
        );
    }

    /**
     * @return array{id: string, success_message: string, edit_url: string|null, editable_until: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'success_message' => $this->successMessage,
            'edit_url' => $this->editUrl,
            'editable_until' => $this->editableUntil,
        ];
    }
}
