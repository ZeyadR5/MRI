<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Display the daily schedule (main page).
     */
    public function index(Request $request)
    {
        $this->cleanupOrphanedCustomSlots();

        $date = $request->get('date', now()->format('Y-m-d'));
        $date = Carbon::parse($date);

        // Branch selection
        $activeBranch = $request->get('branch', session('active_branch', 'zaraeein'));
        if (!in_array($activeBranch, ['zaraeein', 'hadeeth_madina'])) {
            $activeBranch = 'zaraeein';
        }
        session(['active_branch' => $activeBranch]);

        // Get all time slots for this branch
        $timeSlots = TimeSlot::where('branch', $activeBranch)
            ->where(function ($q) use ($date) {
                $q->whereNull('specific_date')
                  ->orWhere('specific_date', $date->format('Y-m-d'));
            })
            ->orderBy('period')
            ->orderBy('category')
            ->orderBy('start_time')
            ->get();

        // Get bookings for this date & branch
        $bookings = Booking::forDate($date)
            ->where('branch', $activeBranch)
            ->with('timeSlot')
            ->get()
            ->keyBy('time_slot_id');

        // Organize slots into sections
        $sections = [
            'morning' => [
                'label' => 'فترة الصبح ☀️',
                'icon' => '🌅',
                'categories' => [
                    'contrast' => ['label' => 'الصبغة', 'slots' => []],
                    'other' => ['label' => 'أخرى', 'slots' => []],
                ],
            ],
            'afternoon' => [
                'label' => 'فترة الضهر 🌤️',
                'icon' => '☀️',
                'categories' => [
                    'spine_brain' => ['label' => 'فقرات ومخ', 'slots' => []],
                    'joints' => ['label' => 'مفاصل', 'slots' => []],
                    'other' => ['label' => 'أخرى', 'slots' => []],
                ],
            ],
            'night' => [
                'label' => 'فترة الليل 🌙',
                'icon' => '🌙',
                'categories' => [
                    'spine_brain' => ['label' => 'فقرات ومخ', 'slots' => []],
                    'joints' => ['label' => 'مفاصل', 'slots' => []],
                    'other' => ['label' => 'أخرى', 'slots' => []],
                ],
            ],
        ];

        foreach ($timeSlots as $slot) {
            if (isset($sections[$slot->period]['categories'][$slot->category])) {
                $sections[$slot->period]['categories'][$slot->category]['slots'][] = [
                    'slot' => $slot,
                    'booking' => $bookings->get($slot->id),
                ];
            }
        }

        // Stats
        $totalSlots = $timeSlots->count();
        $bookedCount = $bookings->filter(fn($b) => $b->patient_name && $b->patient_name !== '0')->count();
        $insuranceCount = $bookings->where('payment_type', 'insurance')->count();
        $cashCount = $bookings->whereIn('payment_type', ['cash', 'contract'])->count();

        return view('schedule.index', compact(
            'date', 'activeBranch', 'sections', 'totalSlots', 'bookedCount',
            'insuranceCount', 'cashCount'
        ));
    }

    /**
     * Store a new booking.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_date' => 'required|date',
            'time_slot_id' => 'nullable|exists:time_slots,id',
            'is_custom' => 'boolean',
            'period' => 'required_if_accepted:is_custom',
            'category' => 'required_if_accepted:is_custom',
            'start_time' => 'required_if_accepted:is_custom',
            'duration' => 'required_if_accepted:is_custom|integer',
            'patient_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'exam_type' => 'nullable|string|max:255',
            'exam_details' => 'nullable|string|max:255',
            'payment_type' => 'required|in:cash,insurance,contract',
            'notes' => 'nullable|string|max:1000',
            'branch' => 'nullable|string|in:zaraeein,hadeeth_madina',
            'force_cross_branch' => 'nullable|boolean',
        ]);

        $branch = $validated['branch'] ?? session('active_branch', 'zaraeein');
        $validated['branch'] = $branch;

        // Check for Cross-Branch Alarm/Warning if patient is booked on same date in another branch
        if (empty($validated['force_cross_branch'])) {
            $crossWarning = $this->checkCrossBranchDuplicate(
                $validated['booking_date'],
                $validated['patient_name'],
                $validated['phone'] ?? null,
                $branch
            );

            if ($crossWarning) {
                return response()->json([
                    'success' => false,
                    'is_cross_branch_warning' => true,
                    'message' => $crossWarning['message'],
                    'other_booking' => $crossWarning['other_booking'],
                ], 409);
            }
        }

        if ($request->has('is_custom') && $request->is_custom) {
            $overlap = $this->checkTimeOverlap(
                $validated['booking_date'],
                $validated['start_time'],
                (int) $validated['duration'],
                $branch
            );

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => $overlap['message'],
                    'errors' => ['start_time' => [$overlap['message']]],
                ], 422);
            }

            // Smart Standard Slot Binding: Check if an unbooked standard slot exists at this exact time
            $formattedTime = substr($validated['start_time'], 0, 5);
            $matchingStandardSlot = TimeSlot::where('branch', $branch)
                ->whereNull('specific_date')
                ->where('is_custom', false)
                ->whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$formattedTime])
                ->first();

            $standardOccupied = false;
            if ($matchingStandardSlot) {
                $standardOccupied = Booking::where('booking_date', $validated['booking_date'])
                    ->where('time_slot_id', $matchingStandardSlot->id)
                    ->where('status', '!=', 'cancelled')
                    ->exists();
            }

            if ($matchingStandardSlot && !$standardOccupied) {
                $validated['time_slot_id'] = $matchingStandardSlot->id;
            } else {
                $timeSlot = TimeSlot::create([
                    'branch' => $branch,
                    'period' => $validated['period'],
                    'category' => $validated['category'],
                    'start_time' => $validated['start_time'],
                    'duration_minutes' => $validated['duration'],
                    'is_custom' => true,
                    'specific_date' => $validated['booking_date'],
                ]);
                $validated['time_slot_id'] = $timeSlot->id;
            }
        } else {
            $existing = Booking::where('booking_date', $validated['booking_date'])
                ->where('time_slot_id', $validated['time_slot_id'])
                ->first();

            $timeSlot = TimeSlot::findOrFail($validated['time_slot_id']);

            $overlap = $this->checkTimeOverlap(
                $validated['booking_date'],
                $timeSlot->start_time,
                (int) $timeSlot->duration_minutes,
                $branch,
                $existing?->id
            );

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => $overlap['message'],
                    'errors' => ['time_slot_id' => [$overlap['message']]],
                ], 422);
            }

            if ($existing) {
                $existing->update($validated);

                AuditLog::record(
                    'تعديل حجز',
                    "تم تحديث حجز المريض ({$validated['patient_name']}) بفرع (" . ($branch === 'zaraeein' ? 'الزراعيين' : 'حديث المدينة') . ")",
                    $branch
                );

                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث الحجز بنجاح',
                    'booking' => $existing->load('timeSlot'),
                ]);
            }
        }

        unset($validated['force_cross_branch'], $validated['is_custom'], $validated['period'], $validated['category'], $validated['start_time'], $validated['duration']);

        $booking = Booking::create($validated);

        AuditLog::record(
            'إنشاء حجز',
            "تم حجز ميعاد للمريض ({$booking->patient_name}) بفرع (" . ($branch === 'zaraeein' ? 'الزراعيين' : 'حديث المدينة') . ") بتاريخ {$booking->booking_date->format('Y-m-d')}",
            $branch
        );

        return response()->json([
            'success' => true,
            'message' => 'تم الحجز بنجاح',
            'booking' => $booking->load('timeSlot'),
        ]);
    }

    /**
     * Update an existing booking.
     */
    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'patient_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'exam_type' => 'nullable|string|max:255',
            'exam_details' => 'nullable|string|max:255',
            'payment_type' => 'required|in:cash,insurance,contract',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:booked,completed,cancelled,no_show',
            'booking_date' => 'nullable|date',
            'start_time' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'force_cross_branch' => 'nullable|boolean',
        ]);

        $branch = $booking->branch;
        $targetDate = isset($validated['booking_date']) ? Carbon::parse($validated['booking_date'])->format('Y-m-d') : $booking->booking_date->format('Y-m-d');
        $currentTimeSlot = $booking->timeSlot;

        $newStartTime = $validated['start_time'] ?? ($currentTimeSlot ? $currentTimeSlot->start_time : null);
        $newDuration = isset($validated['duration']) ? (int) $validated['duration'] : ($currentTimeSlot ? (int) $currentTimeSlot->duration_minutes : 30);

        // Cross branch warning
        if (empty($validated['force_cross_branch'])) {
            $crossWarning = $this->checkCrossBranchDuplicate(
                $targetDate,
                $validated['patient_name'],
                $validated['phone'] ?? null,
                $branch,
                $booking->id
            );

            if ($crossWarning) {
                return response()->json([
                    'success' => false,
                    'is_cross_branch_warning' => true,
                    'message' => $crossWarning['message'],
                    'other_booking' => $crossWarning['other_booking'],
                ], 409);
            }
        }

        $dateChanged = ($targetDate !== $booking->booking_date->format('Y-m-d'));
        $timeChanged = $currentTimeSlot && (
            substr($currentTimeSlot->start_time, 0, 5) !== substr($newStartTime, 0, 5) ||
            (int) $currentTimeSlot->duration_minutes !== $newDuration
        );

        $statusActivating = isset($validated['status']) &&
            in_array($validated['status'], ['booked', 'completed']) &&
            $booking->status === 'cancelled';

        $statusWillBeActive = ($validated['status'] ?? $booking->status) !== 'cancelled';

        if ($newStartTime && $statusWillBeActive && ($dateChanged || $timeChanged || $statusActivating)) {
            $overlap = $this->checkTimeOverlap(
                $targetDate,
                $newStartTime,
                $newDuration,
                $branch,
                $booking->id
            );

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => $overlap['message'],
                    'errors' => ['start_time' => [$overlap['message']]],
                ], 422);
            }
        }

        if (($dateChanged || $timeChanged) && $newStartTime) {
            $formattedTime = substr($newStartTime, 0, 5);

            // Smart Standard Slot Binding: Look for an unbooked standard slot matching target time & date
            $matchingStandardSlot = TimeSlot::where('branch', $branch)
                ->whereNull('specific_date')
                ->where('is_custom', false)
                ->whereRaw("TIME_FORMAT(start_time, '%H:%i') = ?", [$formattedTime])
                ->first();

            $standardOccupied = false;
            if ($matchingStandardSlot) {
                $standardOccupied = Booking::where('booking_date', $targetDate)
                    ->where('time_slot_id', $matchingStandardSlot->id)
                    ->where('id', '!=', $booking->id)
                    ->where('status', '!=', 'cancelled')
                    ->exists();
            }

            if ($matchingStandardSlot && !$standardOccupied) {
                $validated['time_slot_id'] = $matchingStandardSlot->id;
            } else {
                if ($currentTimeSlot && $currentTimeSlot->is_custom && $currentTimeSlot->specific_date) {
                    $currentTimeSlot->update([
                        'start_time' => $newStartTime,
                        'duration_minutes' => $newDuration,
                        'specific_date' => $targetDate,
                    ]);
                    $validated['time_slot_id'] = $currentTimeSlot->id;
                } else {
                    $newSlot = TimeSlot::create([
                        'branch' => $branch,
                        'period' => $currentTimeSlot ? $currentTimeSlot->period : 'morning',
                        'category' => $currentTimeSlot ? $currentTimeSlot->category : 'other',
                        'start_time' => $newStartTime,
                        'duration_minutes' => $newDuration,
                        'is_custom' => true,
                        'specific_date' => $targetDate,
                    ]);
                    $validated['time_slot_id'] = $newSlot->id;
                }
            }
        }

        $validated['booking_date'] = $targetDate;

        unset($validated['start_time'], $validated['duration'], $validated['force_cross_branch']);

        $booking->update($validated);

        $this->cleanupOrphanedCustomSlots();

        AuditLog::record(
            'تعديل حجز',
            "تم تحديث بيانات وميعاد حجز المريض ({$booking->patient_name}) بفرع (" . ($branch === 'zaraeein' ? 'الزراعيين' : 'حديث المدينة') . ")",
            $branch
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث البيانات والميعاد بنجاح',
            'booking' => $booking->load('timeSlot'),
        ]);
    }

    /**
     * Delete a booking.
     */
    public function destroy(Booking $booking)
    {
        $patientName = $booking->patient_name;
        $branch = $booking->branch;
        $timeSlot = $booking->timeSlot;

        $booking->delete();

        if ($timeSlot && $timeSlot->is_custom) {
            $timeSlot->delete();
        }

        $this->cleanupOrphanedCustomSlots();

        AuditLog::record(
            'حذف حجز',
            "تم حذف حجز المريض ({$patientName}) بفرع (" . ($branch === 'zaraeein' ? 'الزراعيين' : 'حديث المدينة') . ")",
            $branch
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الحجز بنجاح',
        ]);
    }

    /**
     * Helper to check for overlapping active bookings within the same branch.
     */
    private function checkTimeOverlap(string $bookingDate, string $startTime, int $durationMinutes, string $branch, ?int $ignoreBookingId = null): ?array
    {
        $dateStr = Carbon::parse($bookingDate)->format('Y-m-d');
        $proposedStart = Carbon::parse($dateStr . ' ' . $startTime);
        $proposedEnd = (clone $proposedStart)->addMinutes($durationMinutes);

        $existingBookings = Booking::where('booking_date', $dateStr)
            ->where('branch', $branch)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('patient_name')
            ->where('patient_name', '!=', '')
            ->where('patient_name', '!=', '0')
            ->when($ignoreBookingId, fn($q) => $q->where('id', '!=', $ignoreBookingId))
            ->with('timeSlot')
            ->get();

        foreach ($existingBookings as $booking) {
            if (!$booking->timeSlot) {
                continue;
            }

            $slotStart = Carbon::parse($dateStr . ' ' . $booking->timeSlot->start_time);
            $slotEnd = (clone $slotStart)->addMinutes((int) $booking->timeSlot->duration_minutes);

            if ($proposedStart < $slotEnd && $slotStart < $proposedEnd) {
                $conflictName = $booking->patient_name;
                $conflictStart = $slotStart->format('H:i');
                $conflictEnd = $slotEnd->format('H:i');
                $pStart = $proposedStart->format('H:i');
                $pEnd = $proposedEnd->format('H:i');

                return [
                    'message' => "تداخل في المواعيد! الموعد المطلوب ({$pStart} - {$pEnd}) يتصادم مع حجز المريض ({$conflictName}) من {$conflictStart} إلى {$conflictEnd}.",
                    'conflict_with' => $conflictName,
                ];
            }
        }

        return null;
    }

    /**
     * Helper to prune custom slots that no longer have any active bookings.
     */
    private function cleanupOrphanedCustomSlots(): void
    {
        TimeSlot::where('is_custom', true)
            ->whereNotNull('specific_date')
            ->whereDoesntHave('bookings')
            ->delete();
    }

    /**
     * Helper to check cross-branch duplicate booking alarm for the same patient.
     */
    private function checkCrossBranchDuplicate(string $bookingDate, string $patientName, ?string $phone, string $currentBranch, ?int $ignoreBookingId = null): ?array
    {
        $dateStr = Carbon::parse($bookingDate)->format('Y-m-d');

        $query = Booking::where('booking_date', $dateStr)
            ->where('branch', '!=', $currentBranch)
            ->where('status', '!=', 'cancelled')
            ->when($ignoreBookingId, fn($q) => $q->where('id', '!=', $ignoreBookingId))
            ->where(function ($q) use ($patientName, $phone) {
                $q->where('patient_name', 'like', "%{$patientName}%");
                if ($phone && strlen($phone) >= 6) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->with('timeSlot');

        $otherBooking = $query->first();

        if ($otherBooking) {
            $otherBranchLabel = $otherBooking->branch === 'zaraeein' ? 'الزراعيين' : 'حديث المدينة';
            $otherTime = $otherBooking->timeSlot ? $otherBooking->timeSlot->formatted_time : '';
            $exam = $otherBooking->exam_type ? "فحص {$otherBooking->exam_type}" : '';

            return [
                'message' => "⚠️ تنبيه: المريض ({$otherBooking->patient_name}) لديه حجز آخر بنفس اليوم بفرع ({$otherBranchLabel}) الساعة ({$otherTime}) {$exam}! هل تريد استكمال الحجز في الفرعين؟",
                'other_booking' => [
                    'id' => $otherBooking->id,
                    'patient_name' => $otherBooking->patient_name,
                    'branch' => $otherBranchLabel,
                    'time' => $otherTime,
                    'exam_type' => $otherBooking->exam_type,
                ],
            ];
        }

        return null;
    }

    /**
     * Search bookings across branches.
     */
    public function search(Request $request)
    {
        $term = $request->get('q', '');

        if (strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $results = Booking::search($term)
            ->with('timeSlot')
            ->orderBy('booking_date', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'patient_name' => $booking->patient_name,
                    'phone' => $booking->phone,
                    'branch' => $booking->branch_label,
                    'date' => $booking->booking_date->format('Y-m-d'),
                    'date_formatted' => $booking->booking_date->format('d/m/Y'),
                    'time' => $booking->timeSlot?->formatted_time,
                    'category' => $booking->timeSlot?->category_label,
                    'exam_type' => $booking->exam_type,
                    'payment_type' => $booking->payment_label,
                    'status' => $booking->status_label,
                    'status_color' => $booking->status_color,
                ];
            });

        return response()->json(['results' => $results]);
    }

    /**
     * Clear old database test data (Super Admin command).
     */
    public function clearOldData(Request $request)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $deletedCount = Booking::where('booking_date', '<', now()->startOfMonth())->delete();

        AuditLog::record('مسح البيانات القديمة', "تم حذف الحجوزات القديمة السابقة لهذا الشهر (عدد الحالات: {$deletedCount})");

        return response()->json([
            'success' => true,
            'message' => "تم حذف {$deletedCount} حالة قديمة بنجاح من قاعدة البيانات",
        ]);
    }
}
