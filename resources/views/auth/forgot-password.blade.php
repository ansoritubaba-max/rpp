@extends('layouts.guest', ['title' => 'Lupa Kata Sandi'])

@section('content')
<div class="w-full max-w-md">
    <a href="{{ route('login') }}" class="mb-6 inline-flex text-sm font-bold text-brand-700">← Kembali ke halaman masuk</a>
    <div class="surface p-6 sm:p-8">
        <p class="eyebrow">Pemulihan akun</p>
        <h1 class="mt-2 text-2xl font-black text-ink-950">Lupa kata sandi?</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Masukkan alamat email akun Anda. Kami akan mengirim tautan untuk membuat kata sandi baru.</p>
        @if(session('status'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @unless($emailReady)<div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Pengiriman email belum aktif. Hubungi pengelola aplikasi untuk memulihkan akun.</div>@endunless
        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">@csrf
            <div><label for="email" class="label">Alamat email</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="field" autocomplete="email" required autofocus>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
            <button class="btn-primary w-full justify-center" @disabled(! $emailReady)>Kirim tautan pengaturan ulang</button>
        </form>
    </div>
</div>
@endsection
