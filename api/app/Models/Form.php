<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\FormSettings;
use App\Enums\FormStatus;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Campos de sistema (versão, status, datas) não são preenchíveis em massa:
 * só as Actions de ciclo de vida os alteram, via forceFill.
 *
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property string|null $description
 * @property string $slug
 * @property FormStatus $status
 * @property array{fields: list<array<string, mixed>>} $schema
 * @property int $schema_version
 * @property bool $has_unpublished_changes
 * @property FormSettings $settings
 * @property int $submissions_count
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'slug', 'schema', 'settings'])]
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'schema' => 'array',
            'schema_version' => 'integer',
            'has_unpublished_changes' => 'boolean',
            'settings' => FormSettings::class,
            'submissions_count' => 'integer',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<FormVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /**
     * @return HasOne<FormVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(FormVersion::class)->latestOfMany('version');
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function hasBeenPublished(): bool
    {
        return $this->schema_version > 0;
    }
}
