@extends('layouts.guest', ['title' => 'Daftar'])

@section('content')
<div class="w-full max-w-md py-8">
    <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-3"><img src="{{ app(\App\Services\Settings\SystemSettings::class)->logoUrl() }}" alt="Logo aplikasi" class="h-11 w-11 rounded-2xl bg-white object-contain shadow-sm ring-1 ring-slate-200"><span class="text-xl font-black tracking-tight text-ink-950">{{ app(\App\Services\Settings\SystemSettings::class)->get('app_name') }}</span></a>
    <div class="surface p-6 sm:p-8">
        <div class="mb-7"><p class="eyebrow">Mulai gratis</p><h1 class="mt-2 text-2xl font-black tracking-tight text-ink-950">Buat akun guru</h1><p class="mt-2 text-sm text-slate-500">{{ $trialDays > 0 ? "Coba fitur selama {$trialDays} hari tanpa pembayaran di awal. Tidak ada tagihan otomatis." : 'Buat akun gratis dan pilih paket sesuai kebutuhan.' }}</p></div>
        @unless($emailReady)<div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">Pendaftaran lewat email belum tersedia. Pengelola perlu mengaktifkan pengiriman email terlebih dahulu agar kode verifikasi benar-benar sampai.</div>@endunless
        @if($emailReady)<p class="mb-5 text-sm leading-6 text-slate-600">Jika pendaftaran dengan Google tidak berhasil, isi formulir ini. Kode verifikasi akan dikirim ke alamat email yang Anda daftarkan, termasuk Gmail.</p>@endif
        <form method="POST" action="{{ route('register.store') }}" class="space-y-4">@csrf
            <div><label for="name" class="label">Nama lengkap</label><input id="name" name="name" value="{{ old('name') }}" class="field" placeholder="Nama dan gelar" autocomplete="name" required>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="email" class="label">Alamat email</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="field" placeholder="nama@gmail.com" autocomplete="email" required>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="label">Kata sandi</label><input id="password" name="password" type="password" class="field" placeholder="Minimal 8 karakter" autocomplete="new-password" required>@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation" class="label">Ulangi kata sandi</label><input id="password_confirmation" name="password_confirmation" type="password" class="field" placeholder="Ketik sekali lagi" autocomplete="new-password" required>@error('password_confirmation')<p class="field-error">{{ $message }}</p>@enderror</div>
            <button class="btn-primary mt-2 w-full" type="submit" @disabled(! $emailReady)>Buat akun saya <x-icon name="arrow-right" /></button>
        </form>
        @if(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
            <div class="my-5 flex items-center gap-3"><span class="h-px flex-1 bg-slate-200"></span><span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">atau</span><span class="h-px flex-1 bg-slate-200"></span></div>
            <a href="{{ route('google.redirect') }}" class="btn-secondary flex w-full justify-center gap-3"><span class="grid h-6 w-6 place-items-center rounded-full bg-white text-sm font-black text-brand-700">G</span> Daftar dengan Google</a>
        @endif
    </div>
    <p class="mt-6 text-center text-sm text-slate-500">Sudah punya akun? <a href="{{ route('login') }}" class="font-bold text-brand-700">Masuk</a></p>
</div>
@endsection
