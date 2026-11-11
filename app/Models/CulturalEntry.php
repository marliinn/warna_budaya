<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CulturalEntry extends Model
{
    use SoftDeletes;

    protected $table = 'cultural_entries';

    protected $guarded = ['id'];

    protected $casts = [
        'keywords' => 'array',
        'verified_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('publication_status', 'published')
            ->where('verification_status', 'verified');
    }

    public function scopeApprovedForPublication(Builder $query): Builder
    {
        return $query->where('publication_status', 'published');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'origin_region_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_cultural_entry',
            'cultural_entry_id',
            'category_id'
        );
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'cultural_entry_id');
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            Source::class,
            'cultural_entry_source',
            'cultural_entry_id',
            'source_id'
        );
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(VerificationLog::class, 'cultural_entry_id');
    }
}
