<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantScimProvider extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'is_enabled',
        'auth_token_hash',
        'auth_token_last8',
        'auto_provision',
        'default_role',
        'deprovision_mode',
        'domain_allowlist',
        'metadata',
        'last_rotated_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'auto_provision' => 'boolean',
            'domain_allowlist' => 'array',
            'metadata' => 'array',
            'last_rotated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
