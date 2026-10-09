@extends('layouts.app')

@section('title', 'جدول الحجز - ' . $date->format('d/m/Y'))

@section('content')
<!-- Date Navigation -->
<div class="date-nav">
    <a href="{{ route('schedule.index', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}" 
       class="date-nav-btn" id="prev-day">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        السابق
    </a>
    <div class="date-display-wrapper" style="display: flex; align-items: center; gap: 10px;">
        <div class="date-display">
            <input type="date" 
                   id="date-picker" 
                   value="{{ $date->format('Y-m-d') }}" 
                   onchange="window.location.href='?date=' + this.value"
                   class="date-input">
            <div class="date-info">
                <span class="date-day">{{ $date->translatedFormat('l') }}</span>
                <span class="date-full">{{ $date->format('d / m / Y') }}</span>
            </div>
        </div>
        
        @if($date->format('Y-m-d') !== now()->format('Y-m-d'))
            <a href="{{ route('schedule.index', ['date' => now()->format('Y-m-d')]) }}" class="btn-primary btn-sm" style="padding: 0.4rem 1rem; border-radius: 20px; text-decoration: none; font-weight: 600;">
                اليوم
            </a>
        @endif
    </div>
    <a href="{{ route('schedule.index', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}" 
       class="date-nav-btn" id="next-day">
        التالي
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
</div>

<!-- Quick Stats -->
<div class="stats-bar">
    <div class="stat-item stat-total">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-text">
            <span class="stat-number" id="stat-booked">{{ $bookedCount }}</span>
            <span class="stat-label">محجوز</span>
        </div>
    </div>
    <div class="stat-item stat-available">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        </div>
        <div class="stat-text">
            <span class="stat-number">{{ $totalSlots - $bookedCount }}</span>
            <span class="stat-label">متاح</span>
        </div>
    </div>
    <div class="stat-item stat-insurance">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="stat-text">
            <span class="stat-number">{{ $insuranceCount }}</span>
            <span class="stat-label">تأمين</span>
        </div>
    </div>
    <div class="stat-item stat-cash">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-text">
            <span class="stat-number">{{ $cashCount }}</span>
            <span class="stat-label">نقدي/تعاقد</span>
        </div>
    </div>
    <button class="print-btn" onclick="window.print()" id="print-btn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        طباعة
    </button>
</div>

<!-- Search Panel (Hidden by default) -->
<div class="search-panel" id="search-panel" style="display: none;">
    <div class="search-box">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="search-input" placeholder="ابحث بالاسم أو رقم التليفون..." autocomplete="off">
        <button class="search-close" onclick="toggleSearch()">✕</button>
    </div>
    <div class="search-results" id="search-results"></div>
</div>

<!-- Stats Panel (Hidden by default) -->
<div class="stats-panel" id="stats-panel" style="display: none;">
    <div class="stats-panel-header">
        <h3>📊 إحصائيات الفترة</h3>
        <button class="search-close" onclick="toggleStats()">✕</button>
    </div>
    <div class="stats-filters">
        <input type="date" id="stats-from" value="{{ now()->startOfMonth()->format('Y-m-d') }}">
        <span>إلى</span>
        <input type="date" id="stats-to" value="{{ now()->format('Y-m-d') }}">
        <button class="btn-primary btn-sm" onclick="loadStats()">عرض</button>
    </div>
    <div class="stats-content" id="stats-content">
        <p class="stats-hint">اضغط "عرض" لتحميل الإحصائيات</p>
    </div>
</div>

<!-- Schedule Sections -->
<div class="schedule-container">
    @foreach($sections as $periodKey => $section)
        <div class="schedule-section period-{{ $periodKey }}">
            <div class="section-header">
                <h2>{{ $section['label'] }}</h2>
            </div>
            
            @foreach($section['categories'] as $catKey => $category)
                @if(count($category['slots']) > 0 || $catKey === 'other')
                <div class="category-block cat-{{ $catKey }}">
                    <div class="category-header">
                        <div>
                            <span class="category-badge">{{ $category['label'] }}</span>
                            <span class="category-count">
                                {{ collect($category['slots'])->filter(fn($s) => $s['booking'] && $s['booking']->patient_name)->count() }}
                                /
                                {{ count($category['slots']) }}
                            </span>
                        </div>
                        <button class="btn-sm btn-secondary" onclick="openCustomBookingModal('{{ $periodKey }}', '{{ $catKey }}')">
                            + إضافة حجز إضافي
                        </button>
                    </div>
                    
                    <div class="slots-grid {{ $catKey === 'joints' ? 'slots-joints' : '' }}">
                        @if(count($category['slots']) === 0)
                            <div style="padding: 20px; color: var(--text-muted); text-align: center; grid-column: 1 / -1; font-size: 0.9rem;">
                                لا يوجد حجوزات هنا حالياً. اضغط "إضافة حجز إضافي" لإضافة ميعاد.
                            </div>
                        @else
                            @foreach($category['slots'] as $item)
                                @php
                                    $slot = $item['slot'];
                                    $booking = $item['booking'];
                                    $isBooked = $booking && $booking->patient_name && $booking->patient_name !== '0';
                                @endphp
                                <div class="slot-card {{ $isBooked ? 'slot-booked' : 'slot-empty' }} {{ $isBooked && $booking->payment_type === 'insurance' ? 'slot-insurance' : '' }}" 
                                     data-slot-id="{{ $slot->id }}"
                                     data-booking-id="{{ $booking?->id }}"
                                     onclick="openBookingModal({{ $slot->id }}, {{ $booking ? json_encode($booking) : 'null' }})"
                                     id="slot-{{ $slot->id }}">
                                    <div class="slot-time">{{ $slot->formatted_time }}</div>
                                    @if($slot->is_custom)
                                        <div class="slot-number" style="color: var(--accent-warning);">إضافي</div>
                                    @else
                                        <div class="slot-number">#{{ $slot->slot_number }}</div>
                                    @endif
                                    @if($isBooked)
                                        <div class="slot-patient">{{ $booking->patient_name }}</div>
                                        <div class="slot-details">
                                            @if($booking->exam_type)
                                                <span class="slot-exam">{{ $booking->exam_type }}</span>
                                            @endif
                                            <span class="slot-payment {{ $booking->payment_type }}">{{ $booking->payment_label }}</span>
                                        </div>
                                        @if($booking->phone)
                                            <div class="slot-phone">📱 {{ $booking->phone }}</div>
                                        @endif
                                        @if($booking->notes)
                                            <div class="slot-notes">📝 {{ $booking->notes }}</div>
                                        @endif
                                        <div class="slot-status" style="--status-color: {{ $booking->status_color }}">
                                            {{ $booking->status_label }}
                                        </div>
                                    @else
                                        <div class="slot-empty-label">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            متاح
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    @endforeach
</div>

<!-- Booking Modal -->
<div class="modal-overlay" id="booking-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">حجز جديد</h3>
            <button class="modal-close" onclick="closeModal()" id="modal-close-btn">✕</button>
        </div>
        <form id="booking-form" onsubmit="saveBooking(event)">
            <input type="hidden" id="form-booking-id">
            <input type="hidden" id="form-time-slot-id">
            <input type="hidden" id="form-is-custom" value="false">
            <input type="hidden" id="form-period">
            <input type="hidden" id="form-category">

            <div class="modal-slot-info" id="modal-slot-info"></div>

            <div id="custom-slot-fields" style="display: none;">
                <div class="form-row-3">
                    <div class="form-group">
                        <label for="form-booking-date">تاريخ الحجز (تلقائي)</label>
                        <input type="date" id="form-booking-date" name="booking_date" value="{{ $date->format('Y-m-d') }}" readonly tabindex="-1" style="pointer-events: none; opacity: 0.8; background: rgba(255, 255, 255, 0.02); cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="form-start-time">وقت الحجز <span class="required">*</span></label>
                        <input type="time" id="form-start-time" name="start_time">
                    </div>
                    <div class="form-group">
                        <label for="form-duration">المدة (دقائق) <span class="required">*</span></label>
                        <input type="number" id="form-duration" name="duration" value="30">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="form-patient-name">اسم المريض <span class="required">*</span></label>
                <input type="text" id="form-patient-name" name="patient_name" required autocomplete="off">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="form-phone">رقم التليفون</label>
                    <input type="text" id="form-phone" name="phone" dir="ltr">
                </div>
                <div class="form-group">
                    <label for="form-exam-type">تفاصيل الفحص</label>
                    <input type="text" id="form-exam-type" name="exam_type" placeholder="مثال: قطنية، عنقية، مخ، ركبة...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="form-payment-type">نوع الدفع</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="payment_type" value="cash" checked>
                            <span class="radio-label cash-label">💵 نقدي</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="payment_type" value="insurance">
                            <span class="radio-label insurance-label">🛡️ تأمين</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="payment_type" value="contract">
                            <span class="radio-label contract-label">📋 تعاقد</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="form-notes">ملاحظات</label>
                <textarea id="form-notes" name="notes" rows="2"></textarea>
            </div>

            <div class="form-group" id="status-group" style="display: none;">
                <label for="form-status">الحالة</label>
                <select id="form-status" name="status">
                    <option value="booked">محجوز</option>
                    <option value="completed">تم الفحص</option>
                    <option value="cancelled">ملغي</option>
                    <option value="no_show">لم يحضر</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn-primary" id="save-booking-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    حفظ
                </button>
                <button type="button" class="btn-danger" id="delete-booking-btn" style="display: none;" onclick="deleteBooking()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    حذف الحجز
                </button>
                <button type="button" class="btn-secondary" onclick="closeModal()">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endsection
