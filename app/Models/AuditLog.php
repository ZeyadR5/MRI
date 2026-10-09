<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'description',
        'ip_address',
        'branch',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to log an audit event cleanly.
     */
    public static function record(string $action, string $description, ?string $branch = null): self
    {
        $user = Auth::user();

        $ip = request()->header('X-Forwarded-For')
           ?? request()->header('X-Real-IP')
           ?? request()->ip()
           ?? request()->server('REMOTE_ADDR')
           ?? '127.0.0.1';

        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        if ($ip === '::1' || empty($ip)) {
            $ip = '127.0.0.1';
        }

        return self::create([
            'user_id' => $user?->id,
            'user_name' => $user ? ($user->name ?? $user->first_name . ' ' . $user->last_name) : 'نظام / زائر',
            'user_role' => $user?->role ?? 'system',
            'action' => $action,
            'description' => $description,
            'ip_address' => $ip,
            'branch' => $branch ?? session('active_branch', 'zaraeein'),
        ]);
    }

    /**
     * Arabic label for action badge.
     */
    public function getActionBadgeAttribute(): string
    {
        return match ($this->action) {
            'إنشاء حجز' => '#10b981',
            'تعديل حجز' => '#3b82f6',
            'حذف حجز' => '#ef4444',
            'تسجيل دخول' => '#6366f1',
            'تسجيل خروج' => '#8b5cf6',
            'إضافة مستخدم' => '#f59e0b',
            'حذف مستخدم' => '#ec4899',
            default => '#6b7280',
        };
    }
}
