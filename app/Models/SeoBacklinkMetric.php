<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoBacklinkMetric extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'seo_property_id',
        'metric_date',
        'referring_domains_total',
        'backlinks_total',
        'lost_referring_domains',
        'new_referring_domains',
        'source_payload',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'referring_domains_total' => 'integer',
            'backlinks_total' => 'integer',
            'lost_referring_domains' => 'integer',
            'new_referring_domains' => 'integer',
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
