<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationBatch extends Model
{
    /** @use HasFactory<\Database\Factories\GenerationBatchFactory> */
    use HasFactory;

    /**
     * After this long without progress an in-flight export is considered dead
     * and a new export may take it over.
     */
    public const EXPORT_STALE_AFTER_SECONDS = 600;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'created_by',
        'idempotency_key',
        'requested_count',
        'generated_count',
        'status',
        'error',
        'started_at',
        'finished_at',
        'export_status',
        'export_format',
        'export_scale',
        'exported_count',
        'export_path',
        'export_error',
        'exported_at',
        'export_claim',
    ];

    protected function casts(): array
    {
        return [
            'status' => BatchStatus::class,
            'export_status' => BatchStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function exportFilename(): string
    {
        if (filled($this->export_path)) {
            return basename($this->export_path);
        }

        return 'batch-'.$this->id.'.'.$this->export_format;
    }

    public function exportIsStale(): bool
    {
        if ($this->export_status !== BatchStatus::Processing) {
            return false;
        }

        return $this->updated_at !== null
            && $this->updated_at->addSeconds(self::EXPORT_STALE_AFTER_SECONDS)->isPast();
    }

    public function progressPercent(): int
    {
        if ($this->requested_count < 1) {
            return 0;
        }

        return (int) min(100, round($this->generated_count / $this->requested_count * 100));
    }

    /**
     * The payload polled by the batch progress row.
     *
     * @return array<string, mixed>
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'requested' => (int) $this->requested_count,
            'generated' => (int) $this->generated_count,
            'export_status' => $this->export_status?->value,
            'export_status_label' => $this->export_status?->label(),
            'exported' => (int) $this->exported_count,
            'error' => $this->error,
            'export_error' => $this->export_error,
            'download_url' => filled($this->export_path) ? route('batches.download', $this) : null,
            'finished' => $this->status === BatchStatus::Completed
                && ($this->export_status === null || $this->export_status === BatchStatus::Completed),
        ];
    }
}
