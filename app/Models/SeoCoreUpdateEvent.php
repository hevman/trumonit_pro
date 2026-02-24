<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoCoreUpdateEvent extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'source_hash',
        'title',
        'source_url',
        'summary',
        'published_at',
        'effective_from',
        'effective_to',
        'impact_score',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'impact_score' => 'integer',
            'metadata' => 'array',
        ];
    }
}
