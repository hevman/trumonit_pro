<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MonitoredUrl extends Model
{
    /** @use HasFactory<\Database\Factories\MonitoredUrlFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'url',
        'display_name',
        'is_active',
        'frequency_minutes',
        'timeout_seconds',
        'follow_redirects',
        'expected_status_min',
        'expected_status_max',
        'last_scanned_at',
        'baseline_snapshot_id',
        'baseline_set_at',
        'scan_mode',
        'journey_steps',
        'content_rules',
        'appearance_min_distance',
        'muted_until',
        'alert_after_failures',
        'alert_cooldown_minutes',
        'next_scan_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'follow_redirects' => 'boolean',
            'alert_after_failures' => 'integer',
            'alert_cooldown_minutes' => 'integer',
            'consecutive_failures' => 'integer',
            'last_scanned_at' => 'datetime',
            'baseline_set_at' => 'datetime',
            'muted_until' => 'datetime',
            'next_scan_at' => 'datetime',
            'journey_steps' => 'array',
            'content_rules' => 'array',
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

    public function snapshots(): HasMany
    {
        return $this->hasMany(UrlSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(UrlSnapshot::class)->latestOfMany('fetched_at');
    }

    public function baselineSnapshot(): BelongsTo
    {
        return $this->belongsTo(UrlSnapshot::class, 'baseline_snapshot_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function statusPages(): BelongsToMany
    {
        return $this->belongsToMany(StatusPage::class, 'status_page_monitored_url')
            ->withPivot(['position', 'label'])
            ->orderByPivot('position');
    }

    public function seoProperties(): HasMany
    {
        return $this->hasMany(SeoProperty::class);
    }
}
