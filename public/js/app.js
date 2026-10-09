/**
 * MRI Booking System - Main JavaScript
 */

// ====== CSRF Token Setup ======
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// ====== Toast Notifications ======
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        ${type === 'success' ? '✅' : '❌'}
        <span>${message}</span>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'toastOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ====== Mobile Menu ======
function toggleMobileMenu() {
    const nav = document.querySelector('.header-nav');
    nav.classList.toggle('show');
}

// ====== Search ======
let searchTimeout;

function toggleSearch() {
    const panel = document.getElementById('search-panel');
    const statsPanel = document.getElementById('stats-panel');
    
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        statsPanel.style.display = 'none';
        document.getElementById('search-input').focus();
    } else {
        panel.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => performSearch(e.target.value), 300);
        });
    }
});

async function performSearch(term) {
    const resultsDiv = document.getElementById('search-results');
    
    if (term.length < 2) {
        resultsDiv.innerHTML = '<p style="padding: 16px; color: var(--text-muted); text-align: center;">اكتب حرفين على الأقل للبحث...</p>';
        return;
    }

    try {
        const response = await fetch(`/search?q=${encodeURIComponent(term)}`);
        const data = await response.json();

        if (data.results.length === 0) {
            resultsDiv.innerHTML = '<p style="padding: 16px; color: var(--text-muted); text-align: center;">لا توجد نتائج</p>';
            return;
        }

        resultsDiv.innerHTML = data.results.map(r => `
            <a href="?date=${r.date}" class="search-result-item" style="text-decoration: none;">
                <div>
                    <div class="search-result-name">${r.patient_name}</div>
                    ${r.phone ? `<div class="search-result-phone">${r.phone}</div>` : ''}
                </div>
                <div class="search-result-date">${r.date_formatted}</div>
                <div style="font-size: 0.82rem; color: var(--text-secondary);">${r.time} - ${r.category}</div>
                <span class="search-result-badge" style="background: ${r.status_color}20; color: ${r.status_color}">${r.status}</span>
            </a>
        `).join('');
    } catch (err) {
        resultsDiv.innerHTML = '<p style="padding: 16px; color: var(--accent-danger);">حدث خطأ في البحث</p>';
    }
}

// ====== Stats ======
function toggleStats() {
    const panel = document.getElementById('stats-panel');
    const searchPanel = document.getElementById('search-panel');
    
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        searchPanel.style.display = 'none';
    } else {
        panel.style.display = 'none';
    }
}

async function loadStats() {
    const from = document.getElementById('stats-from').value;
    const to = document.getElementById('stats-to').value;
    const content = document.getElementById('stats-content');

    content.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--text-muted);">⏳ جاري التحميل...</p>';

    try {
        const response = await fetch(`/stats?from=${from}&to=${to}`);
        const data = await response.json();

        content.innerHTML = `
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-card-number" style="color: var(--accent-primary)">${data.total}</div>
                    <div class="stats-card-label">إجمالي الحالات</div>
                </div>
                <div class="stats-card">
                    <div class="stats-card-number" style="color: var(--accent-info)">${data.insurance}</div>
                    <div class="stats-card-label">تأمين</div>
                </div>
                <div class="stats-card">
                    <div class="stats-card-number" style="color: var(--accent-warning)">${data.cash}</div>
                    <div class="stats-card-label">نقدي</div>
                </div>
                <div class="stats-card">
                    <div class="stats-card-number" style="color: var(--accent-success)">${data.contract}</div>
                    <div class="stats-card-label">تعاقد</div>
                </div>
            </div>
            ${data.by_category ? `
                <div style="margin-top: 1.5rem;">
                    <h4 style="margin-bottom: 0.8rem; color: var(--text-secondary); font-size: 0.9rem;">📋 حسب نوع الفحص</h4>
                    <div class="stats-grid">
                        ${Object.entries(data.by_category).map(([cat, count]) => `
                            <div class="stats-card">
                                <div class="stats-card-number" style="color: var(--accent-secondary)">${count}</div>
                                <div class="stats-card-label">${cat}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        `;
    } catch (err) {
        content.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--accent-danger);">حدث خطأ في تحميل الإحصائيات</p>';
    }
}

// ====== Booking Modal ======
let currentBookingId = null;
let currentSlotId = null;

function openCustomBookingModal(period, category) {
    currentSlotId = null;
    currentBookingId = null;
    const modal = document.getElementById('booking-modal');
    const form = document.getElementById('booking-form');
    const title = document.getElementById('modal-title');
    const slotInfo = document.getElementById('modal-slot-info');
    const deleteBtn = document.getElementById('delete-booking-btn');
    const statusGroup = document.getElementById('status-group');
    const customFields = document.getElementById('custom-slot-fields');

    title.textContent = '➕ حجز ميعاد إضافي';
    form.reset();
    
    document.getElementById('form-time-slot-id').value = '';
    document.getElementById('form-is-custom').value = 'true';
    document.getElementById('form-period').value = period;
    document.getElementById('form-category').value = category;
    
    const activeDate = document.getElementById('date-picker')?.value || new Date().toISOString().split('T')[0];
    document.getElementById('form-booking-date').value = activeDate;
    
    // Set default time to current time rounded to nearest 10 mins
    const now = new Date();
    now.setMinutes(Math.round(now.getMinutes() / 10) * 10);
    const timeString = now.toTimeString().substring(0, 5);
    document.getElementById('form-start-time').value = timeString;
    document.getElementById('form-duration').value = '30';
    
    slotInfo.style.display = 'none';
    customFields.style.display = 'block';
    deleteBtn.style.display = 'none';
    statusGroup.style.display = 'none';

    document.getElementById('form-booking-date').setAttribute('required', 'required');
    document.getElementById('form-start-time').setAttribute('required', 'required');
    document.getElementById('form-duration').setAttribute('required', 'required');

    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('form-patient-name').focus(), 200);
}

function openBookingModal(slotId, booking) {
    currentSlotId = slotId;
    const modal = document.getElementById('booking-modal');
    const form = document.getElementById('booking-form');
    const title = document.getElementById('modal-title');
    const slotInfo = document.getElementById('modal-slot-info');
    const deleteBtn = document.getElementById('delete-booking-btn');
    const statusGroup = document.getElementById('status-group');
    const customFields = document.getElementById('custom-slot-fields');

    // Get slot card info
    const slotCard = document.getElementById(`slot-${slotId}`);
    const slotTime = slotCard?.querySelector('.slot-time')?.textContent || '';
    const slotNumber = slotCard?.querySelector('.slot-number')?.textContent || '';

    // Set hidden fields
    document.getElementById('form-time-slot-id').value = slotId;

    if (booking && booking.patient_name && booking.patient_name !== '0') {
        // Editing existing booking
        currentBookingId = booking.id;
        title.textContent = '✏️ تعديل بيانات وميعاد الحجز';
        document.getElementById('form-booking-id').value = booking.id;
        document.getElementById('form-is-custom').value = booking.time_slot?.is_custom ? 'true' : 'false';
        document.getElementById('form-period').value = booking.time_slot?.period || 'morning';
        document.getElementById('form-category').value = booking.time_slot?.category || 'other';

        // Patient details
        document.getElementById('form-patient-name').value = booking.patient_name || '';
        document.getElementById('form-phone').value = booking.phone || '';
        document.getElementById('form-exam-type').value = booking.exam_type || '';
        document.getElementById('form-notes').value = booking.notes || '';
        
        // Date, time & duration
        let dateVal = booking.booking_date;
        if (typeof dateVal === 'string') {
            dateVal = dateVal.substring(0, 10);
        } else if (dateVal && dateVal.date) {
            dateVal = dateVal.date.substring(0, 10);
        } else {
            dateVal = document.getElementById('date-picker')?.value || '';
        }
        document.getElementById('form-booking-date').value = dateVal;

        const startTimeVal = booking.time_slot?.start_time ? booking.time_slot.start_time.substring(0, 5) : slotTime;
        document.getElementById('form-start-time').value = startTimeVal;

        const durationVal = booking.time_slot?.duration_minutes || 30;
        document.getElementById('form-duration').value = durationVal;

        // Payment type
        const paymentRadio = form.querySelector(`input[name="payment_type"][value="${booking.payment_type}"]`);
        if (paymentRadio) paymentRadio.checked = true;

        // Status
        document.getElementById('form-status').value = booking.status || 'booked';

        // Display info & edit controls
        slotInfo.style.display = 'flex';
        slotInfo.innerHTML = `🕐 الميعاد الحالي: <strong>${slotTime}</strong> &nbsp;|&nbsp; ${slotNumber}`;

        customFields.style.display = 'block';
        deleteBtn.style.display = 'flex';
        statusGroup.style.display = 'block';

        document.getElementById('form-booking-date').setAttribute('required', 'required');
        document.getElementById('form-start-time').setAttribute('required', 'required');
        document.getElementById('form-duration').setAttribute('required', 'required');
    } else {
        // New booking for empty regular slot
        currentBookingId = null;
        title.textContent = '➕ حجز جديد';
        form.reset();

        document.getElementById('form-is-custom').value = 'false';
        document.getElementById('form-time-slot-id').value = slotId;
        const activeDate = document.getElementById('date-picker')?.value || new Date().toISOString().split('T')[0];
        document.getElementById('form-booking-date').value = activeDate;
        document.getElementById('form-patient-name').value = '';

        slotInfo.style.display = 'flex';
        slotInfo.innerHTML = `🕐 الميعاد: <strong>${slotTime}</strong> &nbsp;|&nbsp; ${slotNumber}`;

        customFields.style.display = 'none';
        deleteBtn.style.display = 'none';
        statusGroup.style.display = 'none';

        document.getElementById('form-start-time').removeAttribute('required');
        document.getElementById('form-duration').removeAttribute('required');
    }

    modal.style.display = 'flex';
    
    setTimeout(() => {
        document.getElementById('form-patient-name').focus();
    }, 200);
}

function closeModal() {
    document.getElementById('booking-modal').style.display = 'none';
    currentBookingId = null;
    currentSlotId = null;
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.id === 'booking-modal') {
        closeModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeModal();
    }
});

let pendingBookingFormData = null;

function closeCrossBranchModal() {
    const modal = document.getElementById('cross-branch-modal');
    if (modal) modal.style.display = 'none';
    pendingBookingFormData = null;
}

function confirmForceCrossBranch() {
    if (pendingBookingFormData) {
        pendingBookingFormData.force_cross_branch = true;
        closeCrossBranchModal();
        executeSaveBooking(pendingBookingFormData);
    }
}

async function saveBooking(event) {
    if (event) event.preventDefault();

    const form = document.getElementById('booking-form');
    const formData = {
        booking_date: document.getElementById('form-booking-date').value,
        time_slot_id: document.getElementById('form-time-slot-id').value,
        is_custom: document.getElementById('form-is-custom').value === 'true',
        period: document.getElementById('form-period').value,
        category: document.getElementById('form-category').value,
        start_time: document.getElementById('form-start-time').value,
        duration: document.getElementById('form-duration').value,
        patient_name: document.getElementById('form-patient-name').value.trim(),
        phone: document.getElementById('form-phone').value.trim(),
        exam_type: document.getElementById('form-exam-type').value.trim(),
        payment_type: form.querySelector('input[name="payment_type"]:checked')?.value || 'cash',
        notes: document.getElementById('form-notes').value.trim(),
    };

    if (!formData.patient_name) {
        showToast('يجب إدخال اسم المريض', 'error');
        return;
    }

    if (currentBookingId) {
        formData.status = document.getElementById('form-status').value;
    }

    executeSaveBooking(formData);
}

async function executeSaveBooking(formData) {
    try {
        let url;
        if (currentBookingId) {
            url = `/bookings/${currentBookingId}`;
            formData._method = 'PUT';
        } else {
            url = '/bookings';
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-HTTP-Method-Override': currentBookingId ? 'PUT' : 'POST',
                'Accept': 'application/json',
            },
            body: JSON.stringify(formData),
        });

        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            console.error('Failed to parse JSON response:', parseError);
            throw new Error('Invalid JSON response from server');
        }

        if (response.status === 409 && data.is_cross_branch_warning) {
            pendingBookingFormData = formData;
            const msgContainer = document.getElementById('cross-branch-warning-msg');
            if (msgContainer) msgContainer.innerHTML = data.message;
            const modal = document.getElementById('cross-branch-modal');
            if (modal) modal.style.display = 'flex';
            return;
        }

        if (response.ok && data.success) {
            showToast(data.message);
            closeModal();
            window.location.reload();
        } else {
            let errorMsg = data.message || 'حدث خطأ';
            if (data.errors) {
                const firstErrorKey = Object.keys(data.errors)[0];
                errorMsg = data.errors[firstErrorKey][0];
            }
            showToast(errorMsg, 'error');
        }
    } catch (err) {
        console.error('Save error:', err);
        showToast('حدث خطأ في الاتصال أو الخادم', 'error');
    }
}

async function deleteBooking() {
    if (!currentBookingId) return;

    if (!confirm('هل أنت متأكد من حذف هذا الحجز؟')) return;

    try {
        const response = await fetch(`/bookings/${currentBookingId}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-HTTP-Method-Override': 'DELETE',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                _method: 'DELETE'
            }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            showToast(data.message);
            closeModal();
            window.location.reload();
        } else {
            showToast(data.message || 'حدث خطأ أثناء الحذف', 'error');
        }
    } catch (err) {
        console.error('Delete error:', err);
        showToast('حدث خطأ في الاتصال', 'error');
    }
}

// ====== Keyboard Shortcuts ======
document.addEventListener('keydown', (e) => {
    // Ctrl+F for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        toggleSearch();
    }
    
    // Arrow Left = next day, Arrow Right = prev day (RTL)
    if (!e.target.closest('input, textarea, select, .modal-overlay')) {
        if (e.key === 'ArrowLeft') {
            document.getElementById('next-day')?.click();
        } else if (e.key === 'ArrowRight') {
            document.getElementById('prev-day')?.click();
        }
    }
});
