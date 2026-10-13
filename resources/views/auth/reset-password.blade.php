@extends('layouts.guest', ['title' => 'Atur Ulang Kata Sandi'])

@section('content')
<div class="w-full max-w-md">
    <a href="{{ route('login') }}" class="mb-6 inline-flex text-sm font-bold text-brand-700">← Kembali ke halaman masuk</a>
    <div class="surface p-6 sm:p-8">
        <p class="eyebrow">Pemulihan akun</p>
        <h1 class="mt-2 text-2xl font-black text-ink-950">Buat kata sandi baru</h1>
        <p class="mt-2 text-sm text-slate-500">Gunakan minimal 8 karakter, termasuk huruf dan angka.</p>
        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div><label for="email" class="label">Alamat email</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="field" autocomplete="email" required>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="label">Kata sandi baru</label><input id="password" name="password" type="password" class="field" autocomplete="new-password" required>@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation" class="label">Ulangi kata sandi</label><input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password" required></div>
            <button class="btn-primary w-full justify-center">Simpan kata sandi baru</button>
        </form>
    </div>
</div>
@endsection
