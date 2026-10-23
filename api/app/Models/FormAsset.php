<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormAssetKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Imagem de capa ou logo de um formulário. Imutável: trocar a imagem é enviar
 * outra e apontar o tema para ela; a versão publicada segue com a anterior (D32).
 *
 * @property string $id
 * @property string $form_id
 * @property FormAssetKind $kind
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property Carbon $created_at
 */
class FormAsset extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Imagens do formulário são imutáveis: envie uma nova.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FormAssetKind::class,
            'size' => 'integer',
            'created_at' => 'datetime',
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
