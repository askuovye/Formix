<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LegalDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property LegalDocument $document
 * @property string $version
 * @property Carbon $accepted_at
 * @property string|null $ip_hash
 */
#[Fillable(['document', 'version', 'accepted_at', 'ip_hash'])]
class LegalAcceptance extends Model
{
    use HasUlids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document' => LegalDocument::class,
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
