@extends('layouts.app', ['title' => 'Provider AI'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><p class="eyebrow">AI control center</p><h1 class="mt-1 text-3xl font-black text-ink-950">Provider AI</h1><p class="mt-2 text-sm text-slate-500">Kelola koneksi API yang menjadi sumber model RuangAjar.</p></div>
    <a href="{{ route('admin.ai.providers.create') }}" class="btn-primary"><x-icon name="plus" /> Tambah provider</a>
</div>

@include('admin.ai._nav')

<div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
    @forelse($providers as $provider)
        <article class="surface overflow-hidden">
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div><span class="chip {{ $provider->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $provider->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span><h2 class="mt-3 text-lg font-black text-ink-950">{{ $provider->name }}</h2><p class="mt-1 text-xs font-bold uppercase tracking-wider text-slate-400">{{ $provider->provider_type }}</p></div>
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600"><x-icon name="sparkles" /></span>
                </div>
                <dl class="mt-5 space-y-3 text-xs">
                    <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Model terhubung</dt><dd class="font-bold text-slate-700">{{ $provider->models_count }}</dd></div>
                    <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">API key</dt><dd class="font-mono font-bold text-slate-700">{{ $provider->maskedApiKey() }}</dd></div>
                    <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Timeout</dt><dd class="font-bold text-slate-700">{{ $provider->timeout_seconds }} detik</dd></div>
                </dl>
                <p class="mt-4 truncate rounded-xl bg-slate-50 px-3 py-2.5 font-mono text-[11px] text-slate-500" title="{{ $provider->base_url }}">{{ $provider->base_url }}</p>
            </div>
            <div class="border-t border-slate-100 bg-slate-50/60 p-4"><a href="{{ route('admin.ai.providers.edit', $provider) }}" class="btn-secondary w-full"><x-icon name="edit" /> Edit provider</a></div>
        </article>
    @empty
        <div class="surface col-span-full px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-violet-50 text-violet-600"><x-icon name="sparkles" class="h-7 w-7" /></span><h2 class="mt-4 font-black text-ink-950">Belum ada provider AI</h2><p class="mx-auto mt-2 max-w-md text-sm text-slate-400">Tambahkan satu provider OpenAI-compatible agar model bisa digunakan oleh generator.</p><a href="{{ route('admin.ai.providers.create') }}" class="btn-primary mt-5">Tambah provider pertama</a></div>
    @endforelse
</div>
@endsection
