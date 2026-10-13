@extends('layouts.app', ['title' => $teacher->exists ? 'Edit Guru' : 'Tambah Guru'])

@section('content')
<div class="mx-auto max-w-3xl"><a href="{{ $teacher->exists ? route('admin.teachers.show', $teacher) : route('admin.teachers.index') }}" class="mb-4 inline-flex items-center gap-1 text-xs font-bold text-slate-400 hover:text-brand-700"><x-icon name="arrow-left" class="h-4 w-4" /> Kembali</a><div class="mb-6"><p class="eyebrow">Manajemen pengguna</p><h1 class="mt-1 text-3xl font-black text-ink-950">{{ $teacher->exists ? 'Edit data guru' : 'Tambah guru baru' }}</h1></div>
<form method="POST" action="{{ $teacher->exists ? route('admin.teachers.update', $teacher) : route('admin.teachers.store') }}" class="surface overflow-hidden">@csrf @if($teacher->exists)@method('PUT')@endif
    <div class="grid gap-5 p-6 sm:p-8 md:grid-cols-2">
        <label><span class="label">Nama lengkap</span><input name="name" value="{{ old('name', $teacher->name) }}" class="field" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label><span class="label">Email</span><input name="email" type="email" value="{{ old('email', $teacher->email) }}" class="field" required>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label><span class="label">Nomor telepon</span><input name="phone" value="{{ old('phone', $teacher->phone) }}" class="field" placeholder="08xxxxxxxxxx">@error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label><span class="label">Status akun</span><select name="status" class="field"><option value="active" @selected(old('status', $teacher->status ?: 'active') === 'active')>Aktif</option><option value="suspended" @selected(old('status', $teacher->status) === 'suspended')>Ditangguhkan</option></select></label>
        @unless($teacher->exists)<label><span class="label">Kata sandi</span><input name="password" type="password" class="field" autocomplete="new-password" required>@error('password')<span class="field-error">{{ $message }}</span>@enderror</label><label><span class="label">Konfirmasi kata sandi</span><input name="password_confirmation" type="password" class="field" autocomplete="new-password" required></label>@endunless
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4"><a href="{{ route('admin.teachers.index') }}" class="btn-secondary">Batal</a><button class="btn-primary">Simpan guru</button></div>
</form></div>
@endsection
