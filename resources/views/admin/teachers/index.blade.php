@extends('layouts.app', ['title' => 'Kelola Guru'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Manajemen pengguna</p><h1 class="mt-1 text-3xl font-black tracking-tight text-ink-950">Semua Guru</h1><p class="mt-2 text-sm text-slate-500">Kelola akun, status, paket, dan penggunaan guru.</p></div><a href="{{ route('admin.teachers.create') }}" class="btn-primary"><x-icon name="plus" /> Tambah guru</a></div>

<form method="GET" class="surface mb-6 grid gap-3 p-4 md:grid-cols-[1.4fr_1fr_1fr_auto]">
    <input name="search" value="{{ request('search') }}" class="field" placeholder="Cari nama atau email...">
    <select name="status" class="field"><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="suspended" @selected(request('status') === 'suspended')>Ditangguhkan</option></select>
    <select name="plan" class="field"><option value="">Semua paket</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(request('plan') == $plan->id)>{{ $plan->name }}</option>@endforeach</select>
    <button class="btn-secondary">Terapkan</button>
</form>

<div class="surface overflow-hidden">
    <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-4">Guru</th><th class="px-5 py-4">Sekolah</th><th class="px-5 py-4">Paket</th><th class="px-5 py-4">Penggunaan</th><th class="px-5 py-4">Status</th><th class="px-5 py-4"></th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($teachers as $teacher)<tr class="hover:bg-brand-50/30"><td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-xs font-black text-brand-700">{{ strtoupper(substr($teacher->name, 0, 2)) }}</span><div><p class="font-extrabold text-slate-800">{{ $teacher->name }}</p><p class="text-xs text-slate-400">{{ $teacher->email }}</p></div></div></td><td class="px-5 py-4 text-xs text-slate-500">{{ $teacher->schools->first()?->name ?? 'Belum diisi' }}</td><td class="px-5 py-4"><span class="chip {{ $teacher->activeSubscription ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-500' }}">{{ $teacher->activeSubscription?->plan?->name ?? 'Tanpa paket' }}</span></td><td class="px-5 py-4 text-xs text-slate-500">{{ $teacher->documents_count }} dokumen · {{ $teacher->questions_count }} soal</td><td class="px-5 py-4"><span class="chip {{ $teacher->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $teacher->status === 'active' ? 'Aktif' : 'Ditangguhkan' }}</span></td><td class="px-5 py-4 text-right"><a href="{{ route('admin.teachers.show', $teacher) }}" class="text-xs font-extrabold text-brand-700">Detail</a></td></tr>@empty<tr><td colspan="6" class="px-6 py-14 text-center text-sm text-slate-400">Tidak ada guru yang sesuai dengan filter.</td></tr>@endforelse</tbody></table></div>
</div>
<div class="mt-6">{{ $teachers->links() }}</div>
@endsection
