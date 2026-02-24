<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoIntegration extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'provider',
        'is_enabled',
        'google_client_id',
        'google_client_secret',
        'refresh_token',
        'access_token',
        'access_token_expires_at',
        'scopes',
        'external_account_email',
        'last_synced_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'google_client_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
