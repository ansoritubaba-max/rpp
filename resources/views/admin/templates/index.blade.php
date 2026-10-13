@extends('layouts.app', ['title' => 'Template Dokumen'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><p class="eyebrow">Tata letak cetak</p><h1 class="mt-1 text-3xl font-black text-ink-950">Template Dokumen</h1><p class="mt-2 text-sm text-slate-500">Atur ukuran kertas, tipografi, margin, dan urutan isi PDF untuk setiap perangkat.</p></div>
    <a href="{{ route('admin.templates.create') }}" class="btn-primary"><x-icon name="plus" /> Buat template</a>
</div>

<form method="GET" class="surface mb-6 grid gap-3 p-4 sm:grid-cols-[1fr_180px_auto]">
    <select name="feature" class="field"><option value="">Semua perangkat</option>@foreach($features as $feature)<option value="{{ $feature->id }}" @selected((string) request('feature') === (string) $feature->id)>{{ $feature->name }}</option>@endforeach</select>
    <select name="status" class="field"><option value="">Semua status</option><option value="1" @selected(request('status') === '1')>Aktif</option><option value="0" @selected(request('status') === '0')>Nonaktif</option></select>
    <button class="btn-secondary px-5">Terapkan filter</button>
</form>

<div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
    @forelse($templates as $template)
        <article class="surface flex flex-col overflow-hidden">
            <div class="flex-1 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3"><div><span class="chip {{ $template->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $template->is_active ? 'Aktif' : 'Nonaktif' }}</span><h2 class="mt-3 text-lg font-black text-ink-950">{{ $template->name }}</h2><p class="mt-1 text-xs font-bold text-brand-700">{{ $template->feature->name }}</p></div><span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="file" /></span></div>
                <div class="mt-5 grid grid-cols-2 gap-2 text-xs"><div class="rounded-xl bg-slate-50 p-3"><span class="block text-slate-400">Konteks</span><strong class="mt-1 block text-slate-700">{{ $template->curriculum?->code ?? 'Semua kurikulum' }} · {{ $template->educationLevel?->code ?? 'Semua jenjang' }}</strong></div><div class="rounded-xl bg-slate-50 p-3"><span class="block text-slate-400">Kertas</span><strong class="mt-1 block text-slate-700">{{ strtoupper($template->paper_size) }} · {{ $template->orientation === 'portrait' ? 'Tegak' : 'Mendatar' }}</strong></div><div class="rounded-xl bg-slate-50 p-3"><span class="block text-slate-400">Bagian aktif</span><strong class="mt-1 block text-slate-700">{{ $template->enabled_sections_count }} dari {{ $template->sections_count }}</strong></div><div class="rounded-xl bg-slate-50 p-3"><span class="block text-slate-400">Tipografi</span><strong class="mt-1 block truncate text-slate-700">{{ $template->font_family }} · {{ (float) $template->font_size }} pt</strong></div></div>
            </div>
            <div class="border-t border-slate-100 bg-slate-50/60 p-4"><a href="{{ route('admin.templates.edit', $template) }}" class="btn-secondary w-full"><x-icon name="edit" /> Atur template</a></div>
        </article>
    @empty
        <div class="surface col-span-full px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="file" /></span><h2 class="mt-4 font-black text-ink-950">Belum ada template</h2><p class="mt-2 text-sm text-slate-400">Buat template pertama untuk mengatur hasil PDF.</p></div>
    @endforelse
</div>

@if($templates->hasPages())<div class="mt-6">{{ $templates->links() }}</div>@endif
@endsection
