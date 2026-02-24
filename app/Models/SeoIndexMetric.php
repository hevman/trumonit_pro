<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoIndexMetric extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'seo_property_id',
        'metric_date',
        'indexed_urls',
        'submitted_urls',
        'coverage_ratio',
        'source_payload',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'indexed_urls' => 'integer',
            'submitted_urls' => 'integer',
            'coverage_ratio' => 'float',
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
