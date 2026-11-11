<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Source extends Model
{
    protected $table = 'sources';

    protected $guarded = ['id'];

    protected $casts = [
        'accessed_at' => 'date',
    ];

    public function culturalEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            CulturalEntry::class,
            'cultural_entry_source',
            'source_id',
            'cultural_entry_id'
        );
    }
}
