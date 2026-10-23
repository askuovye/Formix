<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Conteúdo anterior de uma resposta editada. Imutável: é a trilha de auditoria
 * que impede um link de edição vazado de apagar dados sem rastro (D15).
 *
 * @property string $id
 * @property string $submission_id
 * @property array<string, mixed> $data
 * @property list<array{field_id: string, name: string, size: int, mime_type: string}>|null $files
 * @property Carbon $replaced_at
 */
#[Fillable(['data', 'files', 'replaced_at'])]
class SubmissionRevision extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Revisões de resposta são imutáveis.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'files' => 'array',
            'replaced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
