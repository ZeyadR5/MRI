<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام حجز الرنين المغناطيسي</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--bg-primary);
            padding: 1.5rem;
        }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-xl);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 440px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 10;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-brand-icon {
            width: 54px;
            height: 54px;
            margin: 0 auto 12px;
            color: var(--accent-primary);
        }

        .login-brand h1 {
            font-size: 1.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-brand p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .login-form .form-group {
            padding: 0;
            margin-bottom: 1.2rem;
        }

        .login-form label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.88rem;
            color: var(--text-secondary);
        }

        .login-form input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.95rem;
        }

        .login-form input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px var(--accent-primary-glow);
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--accent-primary), #4f46e5);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .login-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--accent-danger);
            padding: 10px 14px;
            border-radius: var(--radius-md);
            font-size: 0.88rem;
            margin-bottom: 1.2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 6v6l4 2" />
                </svg>
            </div>
            <h1>نظام حجز الرنين المغناطيسي</h1>
            <p>سجل الدخول للمتابعة وإدارة المواعيد</p>
        </div>

        @if($errors->has('login'))
            <div class="login-error">
                {{ $errors->first('login') }}
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST" class="login-form">
            @csrf
            <div class="form-group">
                <label for="login">اسم المستخدم أو البريد الإلكتروني</label>
                <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus dir="ltr">
            </div>

            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required dir="ltr">
            </div>

            <button type="submit" class="login-btn">
                تسجيل الدخول
            </button>
        </form>
    </div>
</body>
</html>
