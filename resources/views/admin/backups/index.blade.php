@extends('layouts.app', ['title' => 'Backup'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><p class="eyebrow">Keamanan sistem</p><h1 class="mt-1 text-3xl font-black tracking-tight text-ink-950">Backup</h1><p class="mt-2 text-sm text-slate-500">Snapshot database dan berkas privat penting, hanya untuk admin.</p></div>
    <form method="POST" action="{{ route('admin.backups.store') }}" onsubmit="return confirm('Masukkan backup ke antrean sekarang?')">@csrf<button class="btn-primary">Jadwalkan backup sekarang</button></form>
</div>

@if($errors->has('backup'))<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first('backup') }}</div>@endif
@if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
<div class="surface mb-6 p-5 text-sm leading-6 text-amber-800"><strong>Jaga kerahasiaan arsip.</strong> Backup berisi data akun, dokumen, logo sekolah, dan bukti pembayaran. Simpan salinannya di lokasi aman di luar server ini. Simpan APP_KEY secara terpisah dan aman; kunci tersebut tidak dimasukkan ke arsip. Restore akan mengganti data aktif, jadi unduh dan simpan salinan terbaru sebelum memulihkan backup lama.</div>

<div class="surface mb-6 p-5">
    <h2 class="text-lg font-black text-slate-800">Pulihkan dari berkas ZIP</h2>
    <p class="mt-1 text-sm text-slate-600">Unggah arsip backup RuangAjar yang sebelumnya diunduh. Upload hanya memeriksa arsip; data belum berubah sampai Anda mengonfirmasi di halaman berikutnya.</p>
    <form method="POST" action="{{ route('admin.backups.upload') }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" onsubmit="return checkBackupUploadSize()">@csrf
        <div class="flex-1"><label class="label" for="archive">Arsip backup (.zip)</label><input class="field" id="archive" type="file" name="archive" accept=".zip,application/zip" required>@error('archive')<p class="field-error">{{ $message }}</p>@enderror</div>
        <button class="btn-primary" type="submit">Unggah & periksa</button>
    </form>
    <p class="mt-3 text-xs text-slate-500">Batas upload hosting saat ini: {{ ini_get('upload_max_filesize') }} per berkas, {{ ini_get('post_max_size') }} per permintaan. Jika arsip lebih besar, batas ini perlu dinaikkan di cPanel.</p>
</div>
<script>
function checkBackupUploadSize() {
    const file = document.getElementById('archive').files[0];
    if (!file) return false;
    const toBytes = value => {
        const match = String(value).trim().match(/^(\d+(?:\.\d+)?)\s*([KMG])?B?$/i);
        return match ? Number(match[1]) * Math.pow(1024, { K: 1, M: 2, G: 3 }[(match[2] || '').toUpperCase()] || 0) : Infinity;
    };
    const limit = Math.min(toBytes(@json(ini_get('upload_max_filesize'))), toBytes(@json(ini_get('post_max_size'))), 500 * 1024 * 1024) - 1024;
    if (file.size > limit) {
        alert('Arsip melebihi batas upload hosting. Naikkan upload_max_filesize dan post_max_size di cPanel, lalu coba lagi.');
        return false;
    }
    return true;
}
</script>

<div class="space-y-3">
    @forelse($backups as $backup)
        <article class="surface flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="text-sm font-black text-slate-800">{{ ($backup->started_at ?? $backup->created_at)->locale('id')->translatedFormat('d M Y, H:i') }}</h2><span class="chip {{ $backup->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($backup->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">{{ ['completed' => 'Berhasil', 'failed' => 'Gagal', 'running' => 'Diproses', 'queued' => 'Menunggu antrean'][$backup->status] ?? $backup->status }}</span></div><p class="mt-1 text-xs text-slate-500">{{ $backup->creator?->name ?? 'Sistem / perintah' }} @if($backup->size) · {{ number_format($backup->size / 1024 / 1024, 2, ',', '.') }} MB @endif</p>@if($backup->status === 'failed' && $backup->error_message)<p class="mt-2 text-xs text-rose-600">{{ $backup->error_message }}</p>@endif</div>
            @if($backup->status === 'completed')<div class="flex flex-wrap gap-2"><a href="{{ route('admin.backups.restore', $backup) }}" class="btn-primary shrink-0">Pulihkan</a><a href="{{ route('admin.backups.download', $backup) }}" class="btn-secondary shrink-0">Unduh arsip</a></div>@endif
        </article>
    @empty
        <div class="surface px-6 py-16 text-center text-sm text-slate-500">Belum ada backup. Buat yang pertama untuk melindungi data aplikasi.</div>
    @endforelse
</div>
<div class="mt-6">{{ $backups->links() }}</div>
@endsection
