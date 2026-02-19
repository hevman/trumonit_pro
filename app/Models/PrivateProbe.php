<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrivateProbe extends Model
{
    /** @use HasFactory<\Database\Factories\PrivateProbeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'name',
        'region_hint',
        'capabilities',
        'auth_token_hash',
        'auth_token_last8',
        'is_active',
        'last_seen_at',
        'last_seen_ip',
        'last_seen_user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'capabilities' => 'array',
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scanTasks(): HasMany
    {
        return $this->hasMany(PrivateProbeScanTask::class);
    }
}
