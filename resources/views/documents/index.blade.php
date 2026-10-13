@extends('layouts.app', ['title' => 'Dokumen Saya'])

@section('content')
<div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Arsip pribadi</p><h1 class="mt-1 text-3xl font-black tracking-tight text-ink-950">Dokumen Saya</h1><p class="mt-2 text-sm text-slate-500">Semua perangkat pembelajaran tersimpan dan dapat diunduh kembali.</p></div><a href="{{ route('rpp.create') }}" class="btn-primary self-start"><x-icon name="plus" /> Buat dokumen</a></div>

@if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Filter belum diterapkan: {{ $errors->first() }}</div>@endif
<div class="surface mb-5 p-4">
    <form class="space-y-3" method="GET" action="{{ route('documents.index') }}">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <label class="sm:col-span-2"><span class="label">Cari dokumen</span><input name="search" value="{{ $filters['search'] ?? '' }}" class="field" placeholder="Judul atau materi"></label>
            <label><span class="label">Jenis</span><select name="type" class="field"><option value="">Semua jenis</option>@foreach($types as $type)<option value="{{ $type->code }}" @selected(($filters['type'] ?? '') === $type->code)>{{ $type->name }}</option>@endforeach</select></label>
            <label><span class="label">Status</span><select name="status" class="field"><option value="">Semua status</option>@foreach(['draft' => 'Draf', 'generated' => 'Siap', 'edited' => 'Diedit', 'archived' => 'Diarsipkan'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="label">Mata pelajaran</span><select name="subject_id" class="field"><option value="">Semua mapel</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected((string) ($filters['subject_id'] ?? '') === (string) $subject->id)>{{ $subject->name }}</option>@endforeach</select></label>
            <label><span class="label">Kelas</span><select name="grade_id" class="field"><option value="">Semua kelas</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected((string) ($filters['grade_id'] ?? '') === (string) $grade->id)>{{ $grade->educationLevel?->code }} · {{ $grade->name }}</option>@endforeach</select></label>
            <label><span class="label">Dibuat mulai</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="field"></label>
            <label><span class="label">Dibuat sampai</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="field"></label>
        </div>
        <div class="flex flex-wrap items-center gap-3"><button class="btn-secondary">Terapkan filter</button><a href="{{ route('documents.index') }}" class="text-sm font-semibold text-slate-500 hover:text-brand-700">Hapus filter</a><span class="text-xs text-slate-400">{{ $documents->total() }} dokumen ditemukan</span></div>
    </form>
</div>

<div class="surface overflow-hidden">
    <div class="hidden grid-cols-[1fr_140px_130px_100px] border-b border-slate-100 bg-slate-50/70 px-6 py-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 md:grid"><span>Dokumen</span><span>Diperbarui</span><span>Status</span><span class="text-right">Aksi</span></div>
    @forelse($documents as $document)
        <div class="grid gap-4 border-b border-slate-100 px-4 py-4 last:border-0 hover:bg-brand-50/20 md:grid-cols-[1fr_140px_130px_100px] md:items-center md:px-6">
            <a href="{{ route('documents.show', $document) }}" class="flex min-w-0 items-center gap-3"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-sm font-black text-brand-600">{{ strtoupper(substr($document->feature->name, 0, 2)) }}</span><span class="min-w-0"><strong class="block truncate text-sm text-slate-800">{{ $document->title }}</strong><small class="mt-1 block truncate text-xs text-slate-400">{{ $document->subject?->name }} · {{ $document->grade?->name }} · {{ $document->academic_year }}</small></span></a>
            <span class="text-xs text-slate-500">{{ $document->updated_at->locale('id')->translatedFormat('d M Y') }}<small class="block text-slate-400">{{ $document->updated_at->format('H:i') }} WIB</small></span>
            <span><span class="chip {{ $document->status === 'generated' ? 'bg-emerald-50 text-emerald-700' : 'bg-violet-50 text-violet-700' }}">{{ $document->status === 'generated' ? 'Siap' : ucfirst($document->status) }}</span></span>
            <div class="flex justify-end gap-1">@unless($document->status === 'draft' && data_get($document->metadata_json, 'source') === 'ai-unavailable')<a href="{{ route('documents.pdf', $document) }}" class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-brand-50 hover:text-brand-600" title="Unduh PDF"><x-icon name="download" class="h-4 w-4" /></a>@endunless<a href="{{ route('documents.show', $document) }}" class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-brand-50 hover:text-brand-600" title="Buka"><x-icon name="arrow-right" class="h-4 w-4" /></a></div>
        </div>
    @empty
        <div class="px-6 py-20 text-center"><span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="file" class="h-7 w-7" /></span><h2 class="mt-5 text-lg font-black text-ink-950">{{ collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty() ? 'Tidak ada dokumen yang cocok' : 'Belum ada dokumen' }}</h2><p class="mt-2 text-sm text-slate-400">{{ collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty() ? 'Coba ubah atau hapus filter untuk melihat dokumen lain.' : 'Perangkat yang Anda buat akan muncul di sini.' }}</p><a href="{{ collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty() ? route('documents.index') : route('rpp.create') }}" class="btn-primary mt-6">{{ collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty() ? 'Hapus filter' : 'Buat dokumen pertama' }}</a></div>
    @endforelse
</div>
<div class="mt-5">{{ $documents->links() }}</div>
@endsection
