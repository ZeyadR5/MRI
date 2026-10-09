<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="نظام حجز الرنين المغناطيسي - إدارة المواعيد والحالات">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نظام حجز الرنين المغناطيسي')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @php
        $currentBranch = session('active_branch', 'zaraeein');
    @endphp

    <!-- Header -->
    <header class="main-header">
        <div class="header-container">
            <!-- Right: Brand -->
            <div class="header-brand">
                <a href="{{ route('schedule.index') }}" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon">
                        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="2"/>
                            <path d="M20 8C20 8 12 14 12 20C12 26 16 32 20 32C24 32 28 26 28 20C28 14 20 8 20 8Z" stroke="currentColor" stroke-width="2"/>
                            <line x1="8" y1="20" x2="32" y2="20" stroke="currentColor" stroke-width="2"/>
                            <ellipse cx="20" cy="20" rx="12" ry="6" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 2"/>
                        </svg>
                    </div>
                    <div class="brand-text">
                        <h1>MRI Booking</h1>
                        <span class="brand-subtitle">نظام حجز الرنين المغناطيسي</span>
                    </div>
                </a>
            </div>

            <!-- Center: Branch Switcher -->
            <div class="header-center">
                <div class="branch-switcher">
                    <a href="?branch=zaraeein" class="branch-pill {{ $currentBranch === 'zaraeein' ? 'active' : '' }}">
                        <span>🏥</span>
                        <span>فرع الزراعيين</span>
                    </a>
                    <a href="?branch=hadeeth_madina" class="branch-pill {{ $currentBranch === 'hadeeth_madina' ? 'active' : '' }}">
                        <span>🏢</span>
                        <span>فرع حديث المدينة</span>
                    </a>
                </div>
            </div>

            <!-- Left: Search/Stats + User + Admin Dropdown -->
            <div class="header-left">
                <nav class="header-nav">
                    <button class="nav-btn" id="nav-search" onclick="toggleSearch()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <span>بحث</span>
                    </button>
                    <button class="nav-btn" id="nav-stats" onclick="toggleStats()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <span>إحصائيات</span>
                    </button>
                </nav>

                @auth
                    <div class="user-menu">
                        @if(auth()->user()->isSuperAdmin())
                            <div class="admin-dropdown">
                                <button class="admin-dropdown-btn">
                                    ⚙️ النظام واللوج ▾
                                </button>
                                <div class="admin-dropdown-content">
                                    <a href="{{ route('audit.index') }}">📜 سجل الحركة (Audit Log)</a>
                                    <a href="{{ route('users.index') }}">👥 إدارة المستخدمين</a>
                                    <button type="button" onclick="clearOldData()" style="color: var(--accent-warning);">🧹 مسح الداتا القديمة</button>
                                </div>
                            </div>
                        @endif

                        <div class="user-badge">
                            <span class="user-name">👤 {{ auth()->user()->name }}</span>
                            <span class="user-role-tag" style="background: {{ auth()->user()->role_badge_color }}25; color: {{ auth()->user()->role_badge_color }}">
                                {{ auth()->user()->role_label }}
                            </span>
                        </div>

                        <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                            @csrf
                            <button type="submit" class="logout-btn" title="تسجيل الخروج">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                خروج
                            </button>
                        </form>
                    </div>
                @endauth

                <button class="mobile-menu-btn" id="mobile-menu-btn" onclick="toggleMobileMenu()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        @yield('content')
    </main>

    <!-- Cross-Branch Warning Alarm Modal -->
    <div class="modal-overlay" id="cross-branch-modal" style="display: none;">
        <div class="modal-content" style="max-width: 520px; border: 2px solid var(--accent-warning);">
            <div class="modal-header" style="background: rgba(245, 158, 11, 0.15);">
                <h3 style="color: var(--accent-warning);">⚠️ تنبيه حجز مكرر في فرع آخر</h3>
                <button class="modal-close" onclick="closeCrossBranchModal()">✕</button>
            </div>
            <div style="padding: 24px; font-size: 0.95rem; line-height: 1.8;" id="cross-branch-warning-msg"></div>
            <div class="modal-actions" style="justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-primary" style="background: var(--accent-warning); color: #000; font-weight: 800;" onclick="confirmForceCrossBranch()">
                    تأكيد والحجز في الفرعين
                </button>
                <button type="button" class="btn-secondary" onclick="closeCrossBranchModal()">إلغاء الحجز</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script src="{{ asset('js/app.js') }}"></script>
    <script>
        async function clearOldData() {
            if (!confirm('هل أنت متأكد من مسح كافة الحجوزات القديمة السابقة لهذا الشهر من قاعدة البيانات؟')) return;
            try {
                const res = await fetch('/clear-old-data', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(data.message || 'حدث خطأ', 'error');
                }
            } catch (e) {
                showToast('حدث خطأ في الاتصال', 'error');
            }
        }
    </script>
    @stack('scripts')
</body>
</html>
