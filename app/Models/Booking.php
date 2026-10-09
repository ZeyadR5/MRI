<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'branch',
        'booking_date',
        'time_slot_id',
        'patient_name',
        'phone',
        'exam_type',
        'exam_details',
        'payment_type',
        'notes',
        'status',
    ];

    protected $casts = [
        'booking_date' => 'date',
    ];

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function getBranchLabelAttribute(): string
    {
        return match ($this->branch) {
            'zaraeein' => 'الزراعيين',
            'hadeeth_madina' => 'حديث المدينة',
            default => $this->branch ?? 'الزراعيين',
        };
    }

    public function getPaymentLabelAttribute(): string
    {
        return match ($this->payment_type) {
            'cash' => 'نقدي',
            'insurance' => 'تأمين',
            'contract' => 'تعاقد',
            default => $this->payment_type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'booked' => 'محجوز',
            'completed' => 'تم',
            'cancelled' => 'ملغي',
            'no_show' => 'لم يحضر',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'booked' => '#3b82f6',
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
            'no_show' => '#f59e0b',
            default => '#6b7280',
        };
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('booking_date', $date);
    }

    public function scopeForBranch($query, $branch)
    {
        return $query->where('branch', $branch);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('patient_name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
