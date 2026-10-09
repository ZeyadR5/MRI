<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSlot extends Model
{
    protected $fillable = [
        'branch',
        'period',
        'category',
        'slot_number',
        'start_time',
        'duration_minutes',
        'is_custom',
        'specific_date',
    ];

    protected $casts = [
        'start_time' => 'string',
        'is_custom' => 'boolean',
        'specific_date' => 'date',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getBranchLabelAttribute(): string
    {
        return match ($this->branch) {
            'zaraeein' => 'الزراعيين',
            'hadeeth_madina' => 'حديث المدينة',
            default => $this->branch ?? 'الزراعيين',
        };
    }

    public function getPeriodLabelAttribute(): string
    {
        return match ($this->period) {
            'morning' => 'الصبح',
            'afternoon' => 'الضهر',
            'night' => 'الليل',
            default => $this->period,
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'contrast' => 'الصبغة',
            'spine_brain' => 'فقرات ومخ',
            'joints' => 'مفاصل',
            'other' => 'أخرى',
            default => $this->category,
        };
    }

    public function getFormattedTimeAttribute(): string
    {
        return substr($this->start_time, 0, 5);
    }
}
