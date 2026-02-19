<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StatusPage extends Model
{
    /** @use HasFactory<\Database\Factories\StatusPageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'title',
        'slug',
        'custom_domain',
        'cache_ttl_seconds',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'cache_ttl_seconds' => 'integer',
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

    public function monitoredUrls(): BelongsToMany
    {
        return $this->belongsToMany(MonitoredUrl::class, 'status_page_monitored_url')
            ->withPivot(['position', 'label'])
            ->orderByPivot('position');
    }
}
