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
 * Snapshot imutável de uma publicação. Respostas guardam o número da versão
 * com que foram enviadas; alterar uma versão mudaria o significado delas.
 *
 * @property string $id
 * @property string $form_id
 * @property int $version
 * @property string $title
 * @property string|null $description
 * @property array{fields: list<array<string, mixed>>} $schema
 * @property Carbon $published_at
 */
#[Fillable(['version', 'title', 'description', 'schema', 'published_at'])]
class FormVersion extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Versões publicadas de formulário são imutáveis: publique uma nova versão.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'schema' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
