<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoDailyMetric extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'seo_property_id',
        'metric_date',
        'organic_clicks',
        'organic_impressions',
        'organic_ctr',
        'avg_position',
        'organic_sessions',
        'organic_conversions',
        'source_payload',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'organic_clicks' => 'integer',
            'organic_impressions' => 'integer',
            'organic_ctr' => 'float',
            'avg_position' => 'float',
            'organic_sessions' => 'integer',
            'organic_conversions' => 'integer',
            'source_payload' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function seoProperty(): BelongsTo
    {
        return $this->belongsTo(SeoProperty::class);
    }
}
