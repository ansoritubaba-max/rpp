@extends('layouts.app', ['title' => 'Monitoring AI'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">AI control center</p><h1 class="mt-1 text-3xl font-black text-ink-950">Monitoring AI</h1><p class="mt-2 text-sm text-slate-500">Pantau ketersediaan model, konsumsi token, biaya, dan efektivitas cache.</p></div><a href="{{ route('admin.ai.routing.index') }}" class="btn-primary"><x-icon name="settings" /> Atur routing</a></div>

@include('admin.ai._nav')

<form method="GET" class="surface mb-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
    <select name="period" class="field"><option value="today" @selected(request('period') === 'today')>Hari ini</option><option value="7d" @selected(request('period', '7d') === '7d')>7 hari terakhir</option><option value="30d" @selected(request('period') === '30d')>30 hari terakhir</option><option value="all" @selected(request('period') === 'all')>Semua waktu</option></select>
    <select name="provider" class="field"><option value="">Semua provider</option>@foreach($providers as $provider)<option value="{{ $provider->id }}" @selected((int) request('provider') === $provider->id)>{{ $provider->name }}</option>@endforeach</select>
    <select name="model" class="field"><option value="">Semua model</option>@foreach($models as $model)<option value="{{ $model->id }}" @selected((int) request('model') === $model->id)>{{ $model->name }}</option>@endforeach</select>
    <select name="feature" class="field"><option value="">Semua fitur</option>@foreach($features as $feature)<option value="{{ $feature->id }}" @selected((int) request('feature') === $feature->id)>{{ $feature->name }}</option>@endforeach</select>
    <select name="user" class="field"><option value="">Semua guru</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected((int) request('user') === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select>
    <div class="flex gap-2"><select name="status" class="field"><option value="">Semua status</option><option value="success" @selected(request('status') === 'success')>Berhasil</option><option value="failed" @selected(request('status') === 'failed')>Gagal</option></select><button class="grid min-w-12 place-items-center rounded-xl bg-slate-900 text-white" aria-label="Terapkan filter"><x-icon name="search" /></button></div>
</form>

@php
    $total = (int) ($stats->total ?? 0);
    $successRate = $total > 0 ? round(((int) $stats->success / $total) * 100) : 0;
    $cacheRate = $total > 0 ? round(((int) $stats->cache_hits / $total) * 100) : 0;
@endphp
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-6">
    @foreach([
        ['chart', 'Request', number_format($total), $successRate.'% berhasil', 'bg-brand-50 text-brand-700'],
        ['check', 'Berhasil', number_format($stats->success ?? 0), number_format($stats->failed ?? 0).' gagal', 'bg-emerald-50 text-emerald-700'],
        ['sparkles', 'Token masuk', number_format($stats->input_tokens ?? 0), 'Input', 'bg-violet-50 text-violet-700'],
        ['sparkles', 'Token keluar', number_format($stats->output_tokens ?? 0), 'Output', 'bg-amber-50 text-amber-700'],
        ['payment', 'Estimasi biaya', '$'.number_format((float) ($stats->estimated_cost ?? 0), 4), 'USD', 'bg-cyan-50 text-cyan-700'],
        ['clock', 'Rata-rata', number_format((float) ($stats->average_duration ?? 0)).' ms', $cacheRate.'% cache', 'bg-rose-50 text-rose-700'],
    ] as [$icon, $label, $value, $hint, $color])
        <div class="surface p-4"><span class="grid h-9 w-9 place-items-center rounded-xl {{ $color }}"><x-icon :name="$icon" class="h-4 w-4" /></span><p class="mt-4 text-lg font-black text-ink-950">{{ $value }}</p><div class="mt-1 flex items-center justify-between gap-2 text-[10px]"><span class="font-bold text-slate-500">{{ $label }}</span><span class="text-slate-400">{{ $hint }}</span></div></div>
    @endforeach
</div>

<section class="surface mb-6 overflow-hidden"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-black text-ink-950">Ringkasan provider & model</h2><p class="mt-1 text-xs text-slate-400">Mengikuti periode dan filter yang dipilih.</p></div><div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-xs"><thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3">Provider / model</th><th class="px-4 py-3">Request</th><th class="px-4 py-3">Token masuk</th><th class="px-4 py-3">Token keluar</th><th class="px-4 py-3">Error</th><th class="px-5 py-3">Biaya USD</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($modelSummary as $row)<tr><td class="px-5 py-3 font-bold text-slate-800">{{ $row->provider?->name ?? 'Cache / tanpa provider' }} · {{ $row->model?->name ?? '—' }}</td><td class="px-4 py-3">{{ number_format($row->request_count) }}</td><td class="px-4 py-3">{{ number_format($row->input_tokens) }}</td><td class="px-4 py-3">{{ number_format($row->output_tokens) }}</td><td class="px-4 py-3">{{ number_format($row->error_count) }}</td><td class="px-5 py-3 font-bold">${{ number_format((float) $row->total_cost, 4) }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Belum ada penggunaan pada filter ini.</td></tr>@endforelse</tbody></table></div></section>

<div class="mb-6 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
    <section class="surface overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-black text-ink-950">Kesehatan model</h2><p class="mt-1 text-xs text-slate-400">Status real-time dan tujuan routing aktif.</p></div><a href="{{ route('admin.ai.models.index') }}" class="text-xs font-extrabold text-brand-700">Kelola model</a></div>
        <div class="divide-y divide-slate-100">
            @forelse($models as $model)
                @php $cooling = $model->health?->cooldown_until?->isFuture() ?? false; @endphp
                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $cooling ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' }}"><x-icon name="sparkles" /></span><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><p class="font-black text-slate-800">{{ $model->name }}</p>@if($model->status !== 'active')<span class="chip bg-slate-100 text-slate-500">Nonaktif</span>@elseif($cooling)<span class="chip bg-rose-50 text-rose-700">Cooldown</span>@elseif($model->health?->status === 'healthy' && $model->health?->last_success_at)<span class="chip bg-emerald-50 text-emerald-700">Sehat</span>@else<span class="chip bg-amber-50 text-amber-700">Belum pernah berhasil</span>@endif</div><p class="mt-1 truncate text-xs text-slate-400">{{ $model->provider->name }} · {{ $model->active_routes_count }} routing · {{ $model->health?->last_success_at?->diffForHumans() ?? 'belum pernah berhasil' }}</p>@if($model->health?->last_error)<p class="mt-1 truncate text-[11px] text-rose-500" title="{{ $model->health->last_error }}">{{ $model->health->last_error }}</p>@endif</div>@if($model->health)<form method="POST" action="{{ route('admin.ai.models.health.reset', $model) }}">@csrf<button class="btn-secondary min-h-9 px-3 py-1.5 text-xs">Reset health</button></form>@endif</div>
            @empty
                <div class="px-6 py-12 text-center text-sm text-slate-400">Belum ada model AI. <a href="{{ route('admin.ai.models.create') }}" class="font-bold text-brand-700">Tambahkan model</a>.</div>
            @endforelse
        </div>
    </section>
    <section class="surface p-5 sm:p-6">
        <div class="flex items-center justify-between"><div><h2 class="font-black text-ink-950">Efisiensi cache</h2><p class="mt-1 text-xs text-slate-400">Konten identik dilayani tanpa panggilan API.</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-icon name="package" /></span></div>
        <div class="mt-5 grid grid-cols-3 gap-3"><div class="rounded-xl bg-slate-50 p-3"><p class="text-[9px] font-bold uppercase text-slate-400">Entri</p><p class="mt-2 text-xl font-black text-slate-800">{{ number_format($cacheStats->entries) }}</p></div><div class="rounded-xl bg-emerald-50 p-3"><p class="text-[9px] font-bold uppercase text-emerald-600">Hit</p><p class="mt-2 text-xl font-black text-emerald-800">{{ number_format($cacheStats->hits) }}</p></div><div class="rounded-xl bg-amber-50 p-3"><p class="text-[9px] font-bold uppercase text-amber-600">Kedaluwarsa</p><p class="mt-2 text-xl font-black text-amber-800">{{ number_format($cacheStats->expired) }}</p></div></div>
        <div class="mt-5 space-y-2"><p class="mb-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Konten paling sering dipakai ulang</p>@forelse($topCaches as $cache)<div class="flex items-center justify-between gap-4 rounded-xl border border-slate-100 px-3 py-2.5"><div class="min-w-0"><p class="truncate text-xs font-bold text-slate-700">{{ str($cache->task_key)->headline() }}</p><p class="mt-0.5 text-[10px] text-slate-400">Berakhir {{ $cache->expires_at?->diffForHumans() ?? 'tanpa batas' }}</p></div><span class="chip bg-brand-50 text-brand-700">{{ number_format($cache->hit_count) }} hit</span></div>@empty<div class="rounded-xl bg-slate-50 p-5 text-center text-xs text-slate-400">Cache akan terisi setelah generator AI digunakan.</div>@endforelse</div>
    </section>
</div>

<section class="surface overflow-hidden">
    <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-black text-ink-950">Log penggunaan</h2><p class="mt-1 text-xs text-slate-400">Jejak request sesuai filter aktif.</p></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm"><thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-4">Waktu</th><th class="px-4 py-4">Pengguna / fitur</th><th class="px-4 py-4">Provider / model</th><th class="px-4 py-4">Token</th><th class="px-4 py-4">Durasi</th><th class="px-4 py-4">Biaya</th><th class="px-5 py-4">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($logs as $log)<tr class="hover:bg-slate-50/60"><td class="px-5 py-4"><p class="font-bold text-slate-700">{{ $log->created_at->format('d M Y') }}</p><p class="text-xs text-slate-400">{{ $log->created_at->format('H:i:s') }}</p></td><td class="px-4 py-4"><p class="font-bold text-slate-700">{{ $log->user?->name ?? 'Sistem' }}</p><p class="text-xs text-slate-400">{{ $log->feature?->name ?? 'Fitur dihapus' }}</p></td><td class="px-4 py-4">@if($log->cache_hit)<span class="chip bg-brand-50 text-brand-700">Cache hit</span>@else<p class="font-bold text-slate-700">{{ $log->provider?->name ?? '—' }}</p><p class="text-xs text-slate-400">{{ $log->model?->name ?? '—' }}</p>@endif</td><td class="px-4 py-4 text-xs text-slate-600"><strong>{{ number_format($log->input_tokens) }}</strong> masuk<br><strong>{{ number_format($log->output_tokens) }}</strong> keluar</td><td class="px-4 py-4 text-slate-600">{{ number_format($log->duration_ms) }} ms</td><td class="px-4 py-4 font-bold text-slate-700">${{ number_format((float) $log->estimated_cost, 6) }}</td><td class="px-5 py-4">@if($log->is_probe)<span class="chip mb-1 bg-blue-50 text-blue-700">Uji model</span><br>@endif@if($log->status === 'success')<span class="chip bg-emerald-50 text-emerald-700">Berhasil</span>@else<span class="chip bg-rose-50 text-rose-700" title="{{ $log->error_code }}">Gagal</span><p class="mt-1 max-w-[260px] text-xs text-rose-600">{{ $log->error_message ?? $log->error_code }}</p>@endif</td></tr>@empty<tr><td colspan="7" class="px-6 py-14 text-center text-sm text-slate-400">Belum ada log pada periode ini.</td></tr>@endforelse</tbody></table></div>
    @if($logs->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $logs->links() }}</div>@endif
</section>
@endsection
