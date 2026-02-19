<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSsoProvider extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'provider',
        'is_enabled',
        'issuer_url',
        'authorization_endpoint',
        'token_endpoint',
        'userinfo_endpoint',
        'jwks_uri',
        'client_id',
        'client_secret',
        'private_key',
        'scopes',
        'email_claim',
        'name_claim',
        'subject_claim',
        'domain_allowlist',
        'auto_provision',
        'auto_provision_role',
        'enforce_verified_email',
        'last_tested_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'domain_allowlist' => 'array',
            'auto_provision' => 'boolean',
            'enforce_verified_email' => 'boolean',
            'last_tested_at' => 'datetime',
            'metadata' => 'array',
            'client_secret' => 'encrypted',
            'private_key' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
