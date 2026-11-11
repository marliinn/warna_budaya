<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationLog extends Model
{
    protected $table = 'verification_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function culturalEntry(): BelongsTo
    {
        return $this->belongsTo(CulturalEntry::class, 'cultural_entry_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
