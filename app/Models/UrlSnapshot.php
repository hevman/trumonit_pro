<?php

namespace App\Models;

use App\Services\BillingUsageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\UrlSnapshotFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'monitored_url_id',
        'fetched_at',
        'request_url',
        'final_url',
        'http_status',
        'headers',
        'response_time_ms',
        'body_size',
        'body_bytes_stored',
        'body_sha256',
        'body_storage_path',
        'screenshot_storage_path',
        'screenshot_bytes_stored',
        'storage_tier',
        'archived_at',
        'screenshot_phash',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'archived_at' => 'datetime',
            'headers' => 'array',
            'meta' => 'array',
        ];
    }

    public function monitoredUrl(): BelongsTo
    {
        return $this->belongsTo(MonitoredUrl::class);
    }

    protected static function booted(): void
    {
        static::created(function (UrlSnapshot $snapshot): void {
            app(BillingUsageService::class)->recordSnapshotCreated($snapshot);
        });
    }
}
