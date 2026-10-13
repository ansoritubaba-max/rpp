<?php

namespace App\Http\Controllers;

use App\Services\Auth\SessionDeviceLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountSessionController extends Controller
{
    public function index(Request $request, SessionDeviceLabel $devices): View
    {
        abort_unless(config('session.driver') === 'database', 503, 'Daftar perangkat tidak tersedia pada konfigurasi sesi ini.');

        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'device' => $devices->fromUserAgent($session->user_agent),
                'ip_address' => $session->ip_address,
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->locale('id')->diffForHumans(),
                'current' => $session->id === $request->session()->getId(),
            ]);

        $googleConnected = $request->user()->authIdentities()->where('provider', 'google')->exists();

        return view('account.sessions', compact('sessions', 'googleConnected'));
    }

    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 503);
        abort_if($sessionId === $request->session()->getId(), 422, 'Gunakan tombol Keluar untuk perangkat ini.');

        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        abort_if($deleted === 0, 404);

        return back()->with('success', 'Perangkat tersebut telah dikeluarkan.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 503);

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'Semua perangkat lain telah dikeluarkan.');
    }

}
