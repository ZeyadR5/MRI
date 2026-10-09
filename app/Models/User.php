<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'username',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getNameAttribute()
    {
        if (isset($this->attributes['name']) && $this->attributes['name']) {
            return $this->attributes['name'];
        }
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'super_admin' => 'سوبر أدمن',
            'admin' => 'أدمن',
            'user' => 'يوزر',
            default => $this->role ?? 'يوزر',
        };
    }

    public function getRoleBadgeColorAttribute(): string
    {
        return match ($this->role) {
            'super_admin' => '#ec4899',
            'admin' => '#6366f1',
            'user' => '#10b981',
            default => '#6b7280',
        };
    }
}
