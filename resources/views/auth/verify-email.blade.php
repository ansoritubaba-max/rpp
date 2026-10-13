@extends('layouts.guest', ['title' => 'Verifikasi Email'])

@section('content')
<div class="w-full max-w-md">
    <div class="mb-8 flex items-center justify-center gap-3"><img src="{{ app(\App\Services\Settings\SystemSettings::class)->logoUrl() }}" alt="Logo aplikasi" class="h-11 w-11 rounded-2xl bg-white object-contain shadow-sm ring-1 ring-slate-200"><span class="text-xl font-black tracking-tight text-ink-950">{{ app(\App\Services\Settings\SystemSettings::class)->get('app_name') }}</span></div>
    <div class="surface p-6 sm:p-8">
        <p class="eyebrow">Satu langkah lagi</p>
        <h1 class="mt-2 text-2xl font-black text-ink-950">Verifikasi alamat email</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Masukkan kode 6 digit yang dikirim ke <strong>{{ auth()->user()->email }}</strong>. Kode berlaku selama 10 menit.</p>
        @if(session('status'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @error('google')<div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('verification.verify') }}" class="mt-6 space-y-5">@csrf
            <div><label for="code" class="label">Kode verifikasi</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="field text-center text-xl tracking-[.35em]" required autofocus>@error('code')<p class="field-error">{{ $message }}</p>@enderror</div>
            <button class="btn-primary w-full justify-center">Verifikasi email</button>
        </form>
        <form method="POST" action="{{ route('verification.resend') }}" class="mt-4 text-center">@csrf<button class="text-sm font-bold text-brand-700 hover:underline">Kirim ulang kode</button></form>
        @if(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
            <div class="my-5 flex items-center gap-3"><span class="h-px flex-1 bg-slate-200"></span><span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">atau</span><span class="h-px flex-1 bg-slate-200"></span></div>
            <a href="{{ route('account.google.connect') }}" class="btn-secondary flex w-full justify-center">Verifikasi dengan Google</a>
        @endif
    </div>
    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">@csrf<button class="text-sm font-bold text-slate-500 hover:text-rose-600">Keluar dari akun</button></form>
</div>
@endsection
