@extends('layouts.app', ['title' => 'Pulihkan backup'])

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div><a href="{{ route('admin.backups.index') }}" class="text-sm font-semibold text-brand-700">← Kembali ke daftar backup</a><p class="eyebrow mt-5">Pemulihan data</p><h1 class="mt-1 text-3xl font-black tracking-tight text-ink-950">Pulihkan backup #{{ $backup->id }}</h1><p class="mt-2 text-sm text-slate-500">{{ $backup->type === 'uploaded' ? 'Arsip diunggah' : 'Arsip dibuat' }} {{ ($backup->started_at ?? $backup->created_at)->locale('id')->translatedFormat('d M Y, H:i') }}.</p></div>

    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @error('restore')<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $message }}</div>@enderror
    @if($error)
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800"><strong>Backup tidak aman untuk dipulihkan.</strong> {{ $error }}</div>
    @else
        <div class="surface p-6"><h2 class="text-lg font-black text-slate-800">Pemeriksaan arsip berhasil</h2><p class="mt-2 text-sm text-slate-600">Checksum ZIP, daftar berkas, dan snapshot database cocok. Jenis database: <strong>{{ strtoupper($details['driver']) }}</strong>. Berkas privat: <strong>{{ $details['storage_files'] }}</strong>.</p><p class="mt-2 text-xs text-slate-500">Tanggal dalam arsip: {{ $details['created_at'] ?? 'tidak tercatat' }}.</p><a href="{{ route('admin.backups.download', $backup) }}" class="btn-secondary mt-4 inline-flex">Unduh salinan arsip</a></div>
        @if(! $details['authenticated'])<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Arsip ini dibuat dengan format lama tanpa tanda tangan digital. Integritas berkas telah diperiksa, tetapi sumber arsip tidak dapat dibuktikan. Pulihkan hanya jika arsip berasal dari backup Anda sendiri.</div>@endif
        @if(! $compatible)<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">Jenis database arsip ({{ strtoupper($details['driver']) }}) berbeda dari database aktif ({{ strtoupper($currentDriver) }}). Restore web tidak dapat dijalankan.</div>@endif
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900"><strong>Perhatian:</strong> Restore mengganti database dan logo/bukti pembayaran/branding yang aktif dengan keadaan saat backup dibuat. Perubahan setelah tanggal backup akan hilang. Guru yang sedang bekerja dapat terputus. Pastikan APP_KEY yang digunakan aplikasi tetap sesuai dengan backup.</div>
        @if($compatible)<div class="surface p-6">
            <h2 class="text-lg font-black text-slate-800">Konfirmasi pemulihan</h2>
            <p class="mt-2 text-sm text-slate-600">Sistem akan membuat backup pengaman data saat ini, memulihkan arsip ini, lalu meminta Anda masuk kembali. Proses bisa memakan waktu; jangan tutup halaman atau mengunggah arsip kedua.</p>
            <form method="POST" action="{{ route('admin.backups.restore.execute', $backup) }}" class="mt-5 space-y-4" onsubmit="return confirm('Data aktif akan diganti dengan isi backup ini. Lanjutkan pemulihan?')">@csrf
                <div><label class="label" for="confirmation">Ketik <strong>PULIHKAN-{{ $backup->id }}</strong> untuk melanjutkan</label><input id="confirmation" name="confirmation" class="field" autocomplete="off" required value="{{ old('confirmation') }}">@error('confirmation')<p class="field-error">{{ $message }}</p>@enderror</div>
                <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="acknowledge_loss" value="1" class="mt-1" required><span>Saya paham data yang dibuat setelah backup ini akan tertimpa.</span></label>@error('acknowledge_loss')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn-primary" type="submit">Pulihkan data sekarang</button>
            </form>
            <p class="mt-4 text-xs text-slate-500">Pada hosting dengan batas waktu proses PHP yang sangat pendek, restore arsip besar dapat terhenti. Simpan salinan backup di luar server sebelum memulai.</p>
        </div>@endif
    @endif
</div>
@endsection
