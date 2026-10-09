<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين والصلاحيات</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <style>
        .users-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .users-table th, .users-table td {
            padding: 14px 18px;
            text-align: right;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        .users-table th {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-secondary);
            font-weight: 700;
        }

        .role-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
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
                        <h1>إدارة المستخدمين والصلاحيات</h1>
                        <div class="brand-subtitle">إضافة وتعديل حسابات الموظفين والمسؤولين</div>
                    </div>
                </a>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <a href="{{ route('schedule.index') }}" class="btn-secondary btn-sm">🏠 العودة للجدول الرئيسي</a>
                <a href="{{ route('audit.index') }}" class="btn-secondary btn-sm">📜 سجل الحركة (Audit Log)</a>
            </div>
        </div>
    </header>

    <div class="users-container">
        <div class="page-header">
            <h2>👥 قائمة المستخدمين بالسيستم</h2>
            <button class="btn-primary" onclick="openAddUserModal()">➕ إضافة مستخدم جديد</button>
        </div>

        <table class="users-table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>اسم المستخدم (Username)</th>
                    <th>البريد الإلكتروني</th>
                    <th>مستوى الصلاحية (Role Level)</th>
                    <th>تاريخ الإنشاء</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong></td>
                        <td dir="ltr" style="text-align: right;">{{ $user->username ?? '-' }}</td>
                        <td dir="ltr" style="text-align: right;">{{ $user->email }}</td>
                        <td>
                            <span class="role-badge" style="background: {{ $user->role_badge_color }}20; color: {{ $user->role_badge_color }}">
                                {{ $user->role_label }}
                            </span>
                        </td>
                        <td dir="ltr" style="text-align: right;">{{ $user->created_at?->format('Y-m-d') }}</td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn-sm btn-secondary" onclick="openEditUserModal({{ json_encode($user) }})">✏️ تعديل</button>
                                @if($user->id !== auth()->id())
                                    <button class="btn-sm btn-danger" onclick="deleteUser({{ $user->id }}, '{{ $user->name }}')">🗑️ حذف</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- User Modal -->
    <div class="modal-overlay" id="user-modal" style="display: none;">
        <div class="modal-content" style="max-width: 480px;">
            <div class="modal-header">
                <h3 id="user-modal-title">إضافة مستخدم جديد</h3>
                <button class="modal-close" onclick="closeUserModal()">✕</button>
            </div>
            <form id="user-form" onsubmit="saveUser(event)" style="padding-top: 1rem;">
                <input type="hidden" id="form-user-id">

                <div class="form-group">
                    <label for="form-user-name">الاسم الكامل <span class="required">*</span></label>
                    <input type="text" id="form-user-name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="form-user-username">اسم المستخدم للرئيسي (Username) <span class="required">*</span></label>
                    <input type="text" id="form-user-username" name="username" required dir="ltr">
                </div>

                <div class="form-group">
                    <label for="form-user-email">البريد الإلكتروني <span class="required">*</span></label>
                    <input type="email" id="form-user-email" name="email" required dir="ltr">
                </div>

                <div class="form-group">
                    <label for="form-user-password">كلمة المرور <span id="pass-req-star" class="required">*</span></label>
                    <input type="password" id="form-user-password" name="password" dir="ltr" placeholder="اتركه فارغاً للحفاظ على كلمة المرور القديمة عند التعديل">
                </div>

                <div class="form-group">
                    <label for="form-user-role">مستوى الصلاحيات (Role) <span class="required">*</span></label>
                    <select id="form-user-role" name="role" required>
                        <option value="user">يوزر (تسجيل، عرض، تعديل، حذف)</option>
                        <option value="admin">أدمن (تسجيل، عرض، تعديل، حذف كامل)</option>
                        <option value="super_admin">سوبر أدمن (إدارة اليوزرز + الأوديت لوج + مسح داتا)</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn-primary">حفظ البيانات</button>
                    <button type="button" class="btn-secondary" onclick="closeUserModal()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = '{{ csrf_token() }}';
        let currentEditingUserId = null;

        function openAddUserModal() {
            currentEditingUserId = null;
            document.getElementById('user-modal-title').textContent = '➕ إضافة مستخدم جديد';
            document.getElementById('user-form').reset();
            document.getElementById('form-user-id').value = '';
            document.getElementById('form-user-password').setAttribute('required', 'required');
            document.getElementById('pass-req-star').style.display = 'inline';
            document.getElementById('user-modal').style.display = 'flex';
        }

        function openEditUserModal(user) {
            currentEditingUserId = user.id;
            document.getElementById('user-modal-title').textContent = '✏️ تعديل بيانات المستخدم';
            document.getElementById('form-user-id').value = user.id;
            document.getElementById('form-user-name').value = user.name || '';
            document.getElementById('form-user-username').value = user.username || '';
            document.getElementById('form-user-email').value = user.email || '';
            document.getElementById('form-user-password').value = '';
            document.getElementById('form-user-password').removeAttribute('required');
            document.getElementById('pass-req-star').style.display = 'none';
            document.getElementById('form-user-role').value = user.role || 'user';
            document.getElementById('user-modal').style.display = 'flex';
        }

        function closeUserModal() {
            document.getElementById('user-modal').style.display = 'none';
        }

        async function saveUser(e) {
            e.preventDefault();
            const data = {
                name: document.getElementById('form-user-name').value,
                username: document.getElementById('form-user-username').value,
                email: document.getElementById('form-user-email').value,
                password: document.getElementById('form-user-password').value,
                role: document.getElementById('form-user-role').value,
            };

            const url = currentEditingUserId ? `/users/${currentEditingUserId}` : '/users';
            if (currentEditingUserId) data._method = 'PUT';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });
                const responseData = await res.json();
                if (res.ok && responseData.success) {
                    alert(responseData.message);
                    window.location.reload();
                } else {
                    alert(responseData.message || 'حدث خطأ');
                }
            } catch (err) {
                alert('حدث خطأ في الاتصال');
            }
        }

        async function deleteUser(id, name) {
            if (!confirm(`هل أنت متأكد من حذف حساب (${name})؟`)) return;
            try {
                const res = await fetch(`/users/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ _method: 'DELETE' }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'حدث خطأ');
                }
            } catch (err) {
                alert('حدث خطأ في الاتصال');
            }
        }
    </script>
</body>
</html>
