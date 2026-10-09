<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users (Super Admin only).
     */
    public function index()
    {
        $users = User::orderBy('id', 'desc')->get();
        return view('users.index', compact('users'));
    }

    /**
     * Store a new user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:user,admin,super_admin',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['first_name'] = $validated['name'];
        $validated['last_name'] = '';

        $user = User::create($validated);

        AuditLog::record('إضافة مستخدم', "تمت إضافة مستخدم جديد ({$user->name}) بصلاحية ({$user->role_label})");

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة المستخدم بنجاح',
            'user' => $user,
        ]);
    }

    /**
     * Update user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:user,admin,super_admin',
        ]);

        if (filled($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        AuditLog::record('تعديل مستخدم', "تمت تحديث بيانات المستخدم ({$user->name}) بصلاحية ({$user->role_label})");

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث البيانات بنجاح',
            'user' => $user,
        ]);
    }

    /**
     * Delete user.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك حذف حسابك الحالي',
            ], 422);
        }

        $userName = $user->name;
        $user->delete();

        AuditLog::record('حذف مستخدم', "تم حذف المستخدم ({$userName})");

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المستخدم بنجاح',
        ]);
    }
}
