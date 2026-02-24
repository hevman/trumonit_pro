<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoProperty extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'monitored_url_id',
        'name',
        'domain',
        'gsc_site_url',
        'ga4_property_id',
        'is_enabled',
        'alert_clicks_drop_percent',
        'alert_impressions_drop_percent',
        'alert_sessions_drop_percent',
        'baseline_days',
        'last_evaluated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'alert_clicks_drop_percent' => 'integer',
            'alert_impressions_drop_percent' => 'integer',
            'alert_sessions_drop_percent' => 'integer',
            'baseline_days' => 'integer',
            'last_evaluated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function monitoredUrl(): BelongsTo
    {
        return $this->belongsTo(MonitoredUrl::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(SeoDailyMetric::class);
    }

    public function backlinkMetrics(): HasMany
    {
        return $this->hasMany(SeoBacklinkMetric::class);
    }

    public function indexMetrics(): HasMany
    {
        return $this->hasMany(SeoIndexMetric::class);
    }

    public function usesGsc(): bool
    {
        return $this->seoDataSources()['gsc'];
    }

    public function usesGa4(): bool
    {
        return $this->seoDataSources()['ga4'];
    }

    /**
     * @return array{gsc: bool, ga4: bool}
     */
    public function seoDataSources(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $dataSources = $metadata['data_sources'] ?? null;

        if (is_array($dataSources)) {
            return [
                'gsc' => (bool) ($dataSources['gsc'] ?? false),
                'ga4' => (bool) ($dataSources['ga4'] ?? false),
            ];
        }

        // Backward compatibility for existing records created before data source flags.
        return [
            'gsc' => trim((string) $this->gsc_site_url) !== '',
            'ga4' => trim((string) $this->ga4_property_id) !== '',
        ];
    }
}
