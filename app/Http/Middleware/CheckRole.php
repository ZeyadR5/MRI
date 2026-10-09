<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'غير مصرح'], 401);
            }
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (empty($roles) || in_array($user->role, $roles)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'ليس لديك صلاحية الوصول لهذه الخدمة'], 403);
        }

        abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة');
    }
}
