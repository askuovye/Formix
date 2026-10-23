<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resposta só com mensagem, no mesmo formato `{ "message": ... }` dos erros.
 *
 * @property-read string $resource
 */
final class MessageResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return ['message' => $this->resource];
    }
}
