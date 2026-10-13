<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Settings\SystemSettings;
use Illuminate\Support\Facades\DB;

class SessionLimitService
{
    public function enforce(User $user, string $currentSessionId, SystemSettings $settings): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $limit = max(1, min(10, (int) $settings->get('session_limit')));
        $oldSessionIds = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->orderByDesc('last_activity')
            ->get(['id'])
            ->skip($limit - 1)
            ->pluck('id');

        if ($oldSessionIds->isNotEmpty()) {
            DB::table('sessions')->where('user_id', $user->id)->whereIn('id', $oldSessionIds)->delete();
        }
    }
}
