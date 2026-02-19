<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UrlSnapshotArchive extends Model
{
    /** @use HasFactory<\Database\Factories\UrlSnapshotArchiveFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'monitored_url_id',
        'original_snapshot_id',
        'archived_month',
        'fetched_at',
        'http_status',
        'response_time_ms',
        'body_size',
        'body_bytes_stored',
        'screenshot_bytes_stored',
        'body_storage_path',
        'screenshot_storage_path',
        'storage_tier',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
