<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.audit-logs.index', [
            'logs' => AuditLog::query()
                ->with('user')
                ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', $request->string('action').'%'))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }
}
