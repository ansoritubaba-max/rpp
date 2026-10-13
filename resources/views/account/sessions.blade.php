@extends('layouts.app', ['title' => 'Perangkat Masuk'])

@section('content')
<div class="mx-auto max-w-3xl">
    <p class="eyebrow">Keamanan akun</p>
    <h1 class="mt-1 text-3xl font-black tracking-tight text-ink-950">Perangkat masuk</h1>
    <p class="mt-2 text-sm leading-6 text-slate-500">Lihat perangkat yang masih memiliki sesi aktif. Keluarkan perangkat yang tidak Anda kenali.</p>

    @error('google')<div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>@enderror
    <section class="surface mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="font-black text-ink-950">Login dengan Google</h2><p class="mt-1 text-sm text-slate-500">{{ $googleConnected ? 'Akun Google Anda sudah terhubung.' : 'Hubungkan email yang sama agar dapat masuk tanpa kata sandi.' }}</p></div>
        @if(! $googleConnected && filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
            <a href="{{ route('account.google.connect') }}" class="btn-secondary shrink-0">Hubungkan Google</a>
        @endif
    </section>

    <div class="mt-6 space-y-3">
        @forelse($sessions as $session)
            <div class="surface flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2"><h2 class="font-black text-slate-800">{{ $session['device'] }}</h2>@if($session['current'])<span class="chip bg-emerald-50 text-emerald-700">Perangkat ini</span>@endif</div>
                    <p class="mt-1 text-xs text-slate-500">IP {{ $session['ip_address'] ?: 'Tidak tersedia' }} · Aktif {{ $session['last_active'] }}</p>
                </div>
                @unless($session['current'])
                    <form method="POST" action="{{ route('account.sessions.destroy', $session['id']) }}" onsubmit="return confirm('Keluarkan perangkat ini dari akun Anda?')">@csrf @method('DELETE')<button class="rounded-xl border border-rose-200 px-4 py-2 text-xs font-extrabold text-rose-700 hover:bg-rose-50">Keluarkan</button></form>
                @endunless
            </div>
        @empty
            <div class="surface p-8 text-center text-sm text-slate-500">Belum ada sesi aktif yang tercatat.</div>
        @endforelse
    </div>

    @if($sessions->contains(fn ($session) => ! $session['current']))
        <form method="POST" action="{{ route('account.sessions.others') }}" class="mt-6" onsubmit="return confirm('Keluarkan semua perangkat lain? Anda tetap masuk pada perangkat ini.')">@csrf @method('DELETE')<button class="rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white hover:bg-rose-700">Keluarkan semua perangkat lain</button></form>
    @endif
</div>
@endsection
