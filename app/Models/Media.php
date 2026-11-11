<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    public const PERMISSION_GRANTED = 'granted';

    protected $table = 'media';

    protected $guarded = ['id'];

    protected $casts = [
        'is_cover' => 'boolean',
    ];

    public function culturalEntry(): BelongsTo
    {
        return $this->belongsTo(CulturalEntry::class, 'cultural_entry_id');
    }

    public function publicUrl(): ?string
    {
        if ($this->file_path && str_starts_with($this->file_path, 'cultural/')) {
            return route('cultural-media.show', $this);
        }

        return $this->external_url ?: ($this->file_path ? asset($this->file_path) : null);
    }

    public function licenseUrl(): ?string
    {
        return match ($this->license) {
            'CC BY-SA 4.0' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'CC BY-SA 3.0' => 'https://creativecommons.org/licenses/by-sa/3.0/',
            'CC BY 4.0' => 'https://creativecommons.org/licenses/by/4.0/',
            'CC0 1.0' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'Public domain' => 'https://commons.wikimedia.org/wiki/Commons:Licensing#Public_domain',
            default => null,
        };
    }
}
