<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Campos só alterados pela Action e pelo Job de exportação, via forceFill.
 *
 * @property string $id
 * @property string $user_id
 * @property string $form_id
 * @property ExportStatus $status
 * @property ExportFormat $format
 * @property Carbon|null $from
 * @property Carbon|null $to
 * @property string|null $search
 * @property string|null $respondent_email
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $rows_count
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Export extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'format' => ExportFormat::class,
            'from' => 'datetime',
            'to' => 'datetime',
            'rows_count' => 'integer',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Ready
            && $this->path !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
