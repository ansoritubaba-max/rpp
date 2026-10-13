@extends('layouts.app', ['title' => 'Pengaturan Sistem'])

@section('content')
<div class="mb-6"><p class="eyebrow">Ruang kendali</p><h1 class="mt-1 text-3xl font-black text-ink-950">Pengaturan Sistem</h1><p class="mt-2 text-sm text-slate-500">Atur identitas aplikasi, tampilan dokumen, sesi akun, dan ketersediaan layanan.</p></div>

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">@csrf @method('PUT')
    @if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">Periksa kembali pengaturan: {{ $errors->first() }}</div>@endif
    <section class="surface p-5 sm:p-6"><h2 class="text-lg font-black text-ink-950">Identitas aplikasi</h2><p class="mt-1 text-xs text-slate-500">Gambar PNG, JPG, atau WebP maksimal 2 MB. Logo kop surat sekolah tetap diatur oleh masing-masing guru.</p><div class="mt-5 grid gap-5 md:grid-cols-2">
        <label><span class="label">Nama aplikasi</span><input name="app_name" value="{{ old('app_name', $settings['app_name']) }}" class="field" required maxlength="80"></label>
        <label><span class="label">Email dukungan</span><input name="support_email" type="email" value="{{ old('support_email', $settings['support_email']) }}" class="field" placeholder="bantuan@domain.id"></label>
        <label><span class="label">Logo aplikasi</span><span class="mt-2 flex items-center gap-4"><img src="{{ app(\App\Services\Settings\SystemSettings::class)->logoUrl() }}" alt="Logo saat ini" class="h-16 w-16 rounded-xl object-contain ring-1 ring-slate-200"><input name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-xs"></span></label>
        <label><span class="label">Favicon</span><span class="mt-2 flex items-center gap-4"><img src="{{ app(\App\Services\Settings\SystemSettings::class)->faviconUrl() }}" alt="Favicon saat ini" class="h-16 w-16 rounded-xl object-contain ring-1 ring-slate-200"><input name="favicon" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-xs"></span></label>
    </div></section>

    <section class="surface p-5 sm:p-6"><h2 class="text-lg font-black text-ink-950">Dokumen & waktu</h2><p class="mt-1 text-xs text-slate-500">Pilihan kertas dan margin dipakai bila template dokumen tidak menetapkan nilainya sendiri.</p><div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <label><span class="label">Zona waktu</span><select name="timezone" class="field">@foreach(['Asia/Jakarta' => 'WIB · Jakarta', 'Asia/Makassar' => 'WITA · Makassar', 'Asia/Jayapura' => 'WIT · Jayapura', 'UTC' => 'UTC'] as $value => $label)<option value="{{ $value }}" @selected(old('timezone', $settings['timezone']) === $value)>{{ $label }}</option>@endforeach</select></label>
        <label><span class="label">Ukuran kertas bawaan</span><select name="paper_size" class="field">@foreach(['a4' => 'A4', 'letter' => 'Letter', 'legal' => 'Legal'] as $value => $label)<option value="{{ $value }}" @selected(old('paper_size', $settings['paper_size']) === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="hidden lg:block"></div>
        @foreach(['margin_top' => 'Margin atas', 'margin_right' => 'Margin kanan', 'margin_bottom' => 'Margin bawah', 'margin_left' => 'Margin kiri'] as $key => $label)
            <label><span class="label">{{ $label }} (mm)</span><input name="{{ $key }}" type="number" min="5" max="40" step="0.5" value="{{ old($key, $settings[$key]) }}" class="field" required></label>
        @endforeach
    </div></section>

    <section class="surface p-5 sm:p-6"><h2 class="text-lg font-black text-ink-950">Akses & pemeliharaan</h2><div class="mt-5 grid gap-5 md:grid-cols-2"><label><span class="label">Batas perangkat masuk per akun</span><input name="session_limit" type="number" min="1" max="10" value="{{ old('session_limit', $settings['session_limit']) }}" class="field" required><span class="mt-1 block text-xs text-slate-500">Diterapkan saat login berikutnya. Memerlukan sesi database.</span></label><label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"><input name="maintenance_mode" type="hidden" value="0"><input name="maintenance_mode" type="checkbox" value="1" @checked(old('maintenance_mode', $settings['maintenance_mode'])) class="mt-1 h-4 w-4 rounded border-amber-300 text-amber-700"><span><strong class="block text-sm text-amber-900">Mode pemeliharaan</strong><span class="mt-1 block text-xs leading-5 text-amber-800">Guru sementara tidak dapat membuka ruang kerja. Admin tetap dapat masuk dan memperbaiki sistem.</span></span></label></div></section>

    <section class="surface p-5 sm:p-6"><h2 class="text-lg font-black text-ink-950">Perlindungan generator</h2><p class="mt-1 text-xs text-slate-500">Berlaku untuk pembuatan dokumen dan buat ulang. Batas IP dibuat lebih longgar karena beberapa guru dapat memakai jaringan sekolah yang sama.</p><div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            'generator_user_per_minute' => ['Permintaan per guru / menit', 1, 100],
            'generator_feature_per_minute' => ['Permintaan per fitur / menit', 1, 100],
            'generator_ip_per_minute' => ['Permintaan per IP / menit', 1, 1000],
            'generator_endpoint_per_minute' => ['Permintaan per endpoint / menit', 1, 1000],
            'generator_burst_per_10_seconds' => ['Burst per guru / 10 detik', 1, 30],
            'generator_failure_threshold' => ['Penolakan sebelum blokir / 10 menit', 2, 50],
            'generator_distinct_ip_threshold' => ['IP berbeda sebelum blokir / 10 menit', 2, 50],
            'generator_block_minutes' => ['Durasi blokir sementara (menit)', 1, 1440],
        ] as $key => [$label, $min, $max])
            <label><span class="label">{{ $label }}</span><input name="{{ $key }}" type="number" min="{{ $min }}" max="{{ $max }}" value="{{ old($key, $settings[$key]) }}" class="field" required></label>
        @endforeach
    </div></section>

    <section class="surface p-5 sm:p-6"><h2 class="text-lg font-black text-ink-950">Trial guru baru</h2><p class="mt-1 text-xs text-slate-500">Berlaku sekali saat pendaftaran. Mengubah pengaturan tidak mengubah masa aktif trial yang sudah diberikan.</p><div class="mt-5 grid gap-5 md:grid-cols-2"><label><span class="label">Durasi trial (hari; 0 untuk nonaktif)</span><input name="trial_days" type="number" min="0" max="90" value="{{ old('trial_days', $settings['trial_days']) }}" class="field" required></label><label><span class="label">Paket untuk trial</span><select name="trial_plan_id" class="field"><option value="">Paket aktif termurah</option>@foreach($trialPlans as $plan)<option value="{{ $plan->id }}" @selected((string) old('trial_plan_id', $settings['trial_plan_id']) === (string) $plan->id)>{{ $plan->name }}</option>@endforeach</select></label></div></section>

    <section class="surface p-5 sm:p-6"><h2 class="text-lg font-black text-ink-950">KPI bisnis</h2><p class="mt-1 text-xs text-slate-500">Biaya AI dicatat dalam USD, sementara pendapatan dalam rupiah. Isi kurs acuan agar rasio biaya AI terhadap pendapatan dapat dihitung sebagai estimasi.</p><div class="mt-5 max-w-sm"><label><span class="label">Kurs acuan 1 USD ke rupiah</span><input name="ai_usd_idr_rate" type="number" min="1" max="1000000" step="0.01" value="{{ old('ai_usd_idr_rate', $settings['ai_usd_idr_rate']) }}" class="field" placeholder="Isi kurs yang digunakan bisnis"></label>@error('ai_usd_idr_rate')<p class="field-error">{{ $message }}</p>@enderror<p class="mt-2 text-xs text-slate-500">Diperbarui manual oleh admin; kosongkan bila belum ada kurs yang dapat dipertanggungjawabkan.</p></div></section>

    <div class="flex justify-end"><button class="btn-primary">Simpan pengaturan</button></div>
</form>
@endsection
