<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Nenhum campo é preenchível em massa: as respostas são gravadas só pelas
 * Actions de envio e edição, via forceFill.
 *
 * @property string $id
 * @property string $form_id
 * @property int $schema_version
 * @property array<string, mixed> $data
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property string|null $idempotency_key
 * @property string|null $edit_token_hash
 * @property Carbon|null $editable_until
 * @property Carbon|null $edited_at
 * @property Carbon|null $owner_notified_at
 * @property Carbon|null $respondent_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['ip_hash', 'idempotency_key', 'edit_token_hash'])]
class Submission extends Model implements HasMedia
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory, HasUlids, InteractsWithMedia;

    /**
     * Cada campo de arquivo é uma coleção do MediaLibrary (AGENTS.md §6).
     */
    public static function mediaCollectionFor(string $fieldId): string
    {
        return "field_{$fieldId}";
    }

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'data' => 'array',
            'editable_until' => 'datetime',
            'edited_at' => 'datetime',
            'owner_notified_at' => 'datetime',
            'respondent_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return HasMany<SubmissionRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(SubmissionRevision::class);
    }

    public function isEditable(): bool
    {
        return $this->edit_token_hash !== null
            && $this->editable_until !== null
            && $this->editable_until->isFuture();
    }
}
