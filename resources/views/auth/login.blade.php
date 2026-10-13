@extends('layouts.guest', ['title' => 'Masuk'])

@section('content')
<div class="w-full max-w-md">
    <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-3"><img src="{{ app(\App\Services\Settings\SystemSettings::class)->logoUrl() }}" alt="Logo aplikasi" class="h-11 w-11 rounded-2xl bg-white object-contain shadow-sm ring-1 ring-slate-200"><span class="text-xl font-black tracking-tight text-ink-950">{{ app(\App\Services\Settings\SystemSettings::class)->get('app_name') }}</span></a>
    <div class="surface p-6 sm:p-8">
        @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        <div class="mb-7"><p class="eyebrow">Selamat datang kembali</p><h1 class="mt-2 text-2xl font-black tracking-tight text-ink-950">Masuk ke ruang kerja</h1><p class="mt-2 text-sm text-slate-500">Lanjutkan dokumen pembelajaran Anda.</p></div>
        @error('google')
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                <p>{{ $message }}</p>
                <p class="mt-2">Anda tetap bisa masuk dengan email dan kata sandi, atau <a href="{{ route('register') }}" class="font-bold underline underline-offset-2">daftar lewat formulir</a> jika belum punya akun.</p>
            </div>
        @enderror
        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">@csrf
            <div><label for="email" class="label">Alamat email</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="field" placeholder="nama@sekolah.sch.id" autocomplete="email" required autofocus>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><div class="mb-2 flex justify-between"><label for="password" class="text-sm font-bold text-slate-700">Kata sandi</label><a href="{{ route('password.request') }}" class="text-xs font-bold text-brand-600 hover:underline">Lupa sandi?</a></div><input id="password" name="password" type="password" class="field" placeholder="Minimal 8 karakter" autocomplete="current-password" required></div>
            <label class="flex items-center gap-2.5 text-sm text-slate-500"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600"> Ingat saya di perangkat ini</label>
            <button class="btn-primary w-full" type="submit">Masuk ke RuangAjar <x-icon name="arrow-right" /></button>
        </form>
        @if(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
            <div class="my-5 flex items-center gap-3"><span class="h-px flex-1 bg-slate-200"></span><span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">atau</span><span class="h-px flex-1 bg-slate-200"></span></div>
            <a href="{{ route('google.redirect') }}" class="btn-secondary flex w-full justify-center gap-3"><span class="grid h-6 w-6 place-items-center rounded-full bg-white text-sm font-black text-brand-700">G</span> Lanjutkan dengan Google</a>
        @endif
    </div>
    <p class="mt-6 text-center text-sm text-slate-500">Belum punya akun? <a href="{{ route('register') }}" class="font-bold text-brand-700">Daftar gratis</a></p>
</div>
@endsection
