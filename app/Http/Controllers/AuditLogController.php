<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs (Super Admin only).
     */
    public function index(Request $request)
    {
        $query = AuditLog::query()->orderBy('id', 'desc');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('user_name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%")
                  ->orWhere('ip_address', 'like', "%{$term}%");
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
