<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'role'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $role === self::ROLE_SUPER_ADMIN
            && $this->role === self::ROLE_SUPER_ADMIN
            && $this->is_active !== false;
    }

    public function createdCulturalEntries(): HasMany
    {
        return $this->hasMany(CulturalEntry::class, 'created_by');
    }

    public function updatedCulturalEntries(): HasMany
    {
        return $this->hasMany(CulturalEntry::class, 'updated_by');
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(VerificationLog::class, 'reviewer_id');
    }
}
