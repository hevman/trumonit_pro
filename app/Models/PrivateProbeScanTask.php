<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateProbeScanTask extends Model
{
    /** @use HasFactory<\Database\Factories\PrivateProbeScanTaskFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'private_probe_id',
        'monitored_url_id',
        'requested_by_user_id',
        'status',
        'priority',
        'lease_token_hash',
        'lease_expires_at',
        'claimed_at',
        'result_received_at',
        'completed_at',
        'result_payload',
        'meta',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'lease_expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'result_received_at' => 'datetime',
            'completed_at' => 'datetime',
            'result_payload' => 'array',
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function privateProbe(): BelongsTo
    {
        return $this->belongsTo(PrivateProbe::class);
    }

    public function monitoredUrl(): BelongsTo
    {
        return $this->belongsTo(MonitoredUrl::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
