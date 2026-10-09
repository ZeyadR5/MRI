<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل العمليات والأحداث التفصيلي (Audit Log)</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <style>
        .audit-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .audit-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .audit-table th, .audit-table td {
            padding: 14px 18px;
            text-align: right;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.88rem;
        }

        .audit-table th {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-secondary);
            font-weight: 700;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-bar input, .filter-bar select {
            padding: 8px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: inherit;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-container">
            <div class="header-brand">
                <a href="{{ route('schedule.index') }}" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </div>
                    <div class="brand-text">
                        <h1>سجل العمليات والأحداث التفصيلي (Audit Log)</h1>
                        <div class="brand-subtitle">نظام تتبع وحماية حركة المستخدمين</div>
                    </div>
                </a>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <a href="{{ route('schedule.index') }}" class="btn-secondary btn-sm">🏠 العودة للجدول الرئيسي</a>
                @if(auth()->user()?->isSuperAdmin())
                    <a href="{{ route('users.index') }}" class="btn-secondary btn-sm">👥 إدارة المستخدمين</a>
                @endif
            </div>
        </div>
    </header>

    <div class="audit-container">
        <div class="page-header">
            <h2>📜 سجل الحركة والعمليات</h2>
            <div style="color: var(--text-muted); font-size: 0.9rem;">
                إجمالي التداولات السجلية: <strong>{{ $logs->total() }}</strong>
            </div>
        </div>

        <form method="GET" action="{{ route('audit.index') }}" class="filter-bar">
            <input type="text" name="search" placeholder="بحث باسم الموظف، العملية، أو الـ IP..." value="{{ request('search') }}" style="flex: 1; min-width: 250px;">
            <select name="action">
                <option value="">كل الإجراءات</option>
                <option value="إنشاء حجز" {{ request('action') === 'إنشاء حجز' ? 'selected' : '' }}>إنشاء حجز</option>
                <option value="تعديل حجز" {{ request('action') === 'تعديل حجز' ? 'selected' : '' }}>تعديل حجز</option>
                <option value="حذف حجز" {{ request('action') === 'حذف حجز' ? 'selected' : '' }}>حذف حجز</option>
                <option value="تسجيل دخول" {{ request('action') === 'تسجيل دخول' ? 'selected' : '' }}>تسجيل دخول</option>
                <option value="تسجيل خروج" {{ request('action') === 'تسجيل خروج' ? 'selected' : '' }}>تسجيل خروج</option>
            </select>
            <input type="date" name="date" value="{{ request('date') }}">
            <button type="submit" class="btn-primary btn-sm">فلترة</button>
            <a href="{{ route('audit.index') }}" class="btn-secondary btn-sm" style="text-decoration: none;">إلغاء الفلترة</a>
        </form>

        <table class="audit-table">
            <thead>
                <tr>
                    <th>الوقت والتاريخ</th>
                    <th>الموظف / اسم المستخدم</th>
                    <th>الصلاحية</th>
                    <th>نوع الإجراء</th>
                    <th>تفاصيل العملية وداتا الحركة</th>
                    <th>الفرع</th>
                    <th>عنوان الـ IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td dir="ltr" style="text-align: right; font-weight: 600; color: var(--accent-secondary);">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td>
                            <strong>{{ $log->user_name }}</strong>
                        </td>
                        <td>
                            <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: var(--accent-primary);">
                                {{ $log->user_role }}
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background: {{ $log->action_badge }}20; color: {{ $log->action_badge }}">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td>{{ $log->description }}</td>
                        <td>
                            <span class="badge" style="background: rgba(255, 255, 255, 0.08); color: var(--text-primary);">
                                {{ $log->branch === 'zaraeein' ? 'الزراعيين' : ($log->branch === 'hadeeth_madina' ? 'حديث المدينة' : 'الكل') }}
                            </span>
                        </td>
                        <td dir="ltr" style="text-align: right; color: var(--text-muted); font-weight: 600;">
                            {{ $log->ip_address ?: '127.0.0.1' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            لا توجد سجلات مطابقة للبحث حالياً.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 1.5rem;">
            {{ $logs->links() }}
        </div>
    </div>
</body>
</html>
