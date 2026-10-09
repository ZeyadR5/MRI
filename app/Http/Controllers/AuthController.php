<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Show login form.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('schedule.index');
        }
        return view('auth.login');
    }

    /**
     * Process login request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $fieldType = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$fieldType => $credentials['login'], 'password' => $credentials['password']], $request->remember)) {
            $request->session()->regenerate();

            // Record audit log
            AuditLog::record('تسجيل دخول', 'قام الموظف بتسجيل الدخول إلى النظام');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تسجيل الدخول بنجاح',
                    'redirect' => route('schedule.index'),
                ]);
            }

            return redirect()->intended(route('schedule.index'));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة',
            ], 422);
        }

        return back()->withErrors([
            'login' => 'اسم المستخدم أو كلمة المرور غير صحيحة',
        ])->onlyInput('login');
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        if (Auth::check()) {
            AuditLog::record('تسجيل خروج', 'قام الموظف بتسجيل الخروج من النظام');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
