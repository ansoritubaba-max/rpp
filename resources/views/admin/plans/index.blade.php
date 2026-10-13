@extends('layouts.app', ['title' => 'Paket & Limit'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Produk berlangganan</p><h1 class="mt-1 text-3xl font-black text-ink-950">Paket & Limit</h1><p class="mt-2 text-sm text-slate-500">Atur harga, durasi, fitur, dan kuota tanpa perubahan kode.</p></div><a href="{{ route('admin.plans.create') }}" class="btn-primary"><x-icon name="plus" /> Buat paket</a></div>

<div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
    @forelse($plans as $plan)
        <article class="surface flex flex-col overflow-hidden"><div class="border-b border-slate-100 p-5 sm:p-6"><div class="flex items-start justify-between gap-3"><div><span class="chip {{ $plan->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span><h2 class="mt-3 text-xl font-black text-ink-950">{{ $plan->name }}</h2></div><span class="grid h-11 w-11 place-items-center rounded-2xl bg-violet-50 text-violet-600"><x-icon name="package" /></span></div><p class="mt-3 min-h-10 text-sm leading-5 text-slate-500">{{ $plan->description ?: 'Tanpa deskripsi.' }}</p><p class="mt-5 text-2xl font-black text-ink-950">Rp {{ number_format($plan->price, 0, ',', '.') }} <span class="text-xs font-semibold text-slate-400">/ {{ $plan->duration_days }} hari</span></p></div>
            <div class="flex-1 p-5 sm:p-6"><div class="mb-4 flex items-center justify-between text-xs"><span class="font-bold text-slate-500">{{ $plan->features->count() }} fitur aktif</span><span class="text-slate-400">{{ $plan->active_subscriptions_count }} pelanggan</span></div><div class="space-y-2">@foreach($plan->features->take(6) as $feature)<div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs"><span class="font-bold text-slate-600">{{ $feature->name }}</span><span class="text-slate-400">{{ $feature->pivot->generation_limit ?? '∞' }} generate</span></div>@endforeach @if($plan->features->count() > 6)<p class="pt-1 text-center text-[11px] font-bold text-slate-400">+{{ $plan->features->count() - 6 }} fitur lainnya</p>@endif</div></div>
            <div class="border-t border-slate-100 bg-slate-50/60 p-4"><a href="{{ route('admin.plans.edit', $plan) }}" class="btn-secondary w-full"><x-icon name="edit" /> Edit paket & limit</a></div>
        </article>
    @empty
        <div class="surface col-span-full px-6 py-16 text-center"><h2 class="font-black text-ink-950">Belum ada paket</h2><p class="mt-2 text-sm text-slate-400">Buat paket pertama untuk mulai mengaktifkan guru.</p></div>
    @endforelse
</div>
@endsection
