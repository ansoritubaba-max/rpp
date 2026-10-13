@extends('layouts.app', ['title' => 'Routing AI'])

@section('content')
<div class="mb-6"><p class="eyebrow">AI control center</p><h1 class="mt-1 text-3xl font-black text-ink-950">Routing & failover</h1><p class="mt-2 max-w-3xl text-sm text-slate-500">Pilih model untuk setiap fitur. Angka prioritas terkecil dicoba lebih dulu; jika gagal, sistem otomatis beralih ke model berikutnya.</p></div>

@include('admin.ai._nav')

@if($models->isEmpty())
    <div class="surface px-6 py-16 text-center"><h2 class="font-black text-ink-950">Belum ada model untuk dirutekan</h2><p class="mt-2 text-sm text-slate-400">Tambahkan minimal satu model AI sebelum mengatur failover.</p><a href="{{ route('admin.ai.models.create') }}" class="btn-primary mt-5">Tambah model</a></div>
@else
<form method="POST" action="{{ route('admin.ai.routing.update') }}" class="space-y-5">
    @csrf @method('PUT')
    @foreach($features as $feature)
        <section class="surface overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-black text-ink-950">{{ $feature->name }}</h2><p class="mt-1 text-xs text-slate-400">{{ $feature->description }}</p></div><span class="chip bg-white text-slate-500 ring-1 ring-slate-200">{{ $feature->aiModels->count() }} model aktif</span></div>
            <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($models as $model)
                    @php
                        $configured = $feature->aiModels->firstWhere('id', $model->id);
                        $enabled = (bool) old("routes.{$feature->id}.{$model->id}.enabled", $configured ? 1 : 0);
                        $priority = old("routes.{$feature->id}.{$model->id}.priority", $configured?->pivot?->priority ?? $model->priority);
                    @endphp
                    <div class="rounded-2xl border {{ $enabled ? 'border-brand-200 bg-brand-50/40' : 'border-slate-200 bg-white' }} p-4">
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="routes[{{ $feature->id }}][{{ $model->id }}][enabled]" value="0">
                            <input id="route-{{ $feature->id }}-{{ $model->id }}" type="checkbox" name="routes[{{ $feature->id }}][{{ $model->id }}][enabled]" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked($enabled)>
                            <label for="route-{{ $feature->id }}-{{ $model->id }}" class="min-w-0 flex-1 cursor-pointer"><span class="block truncate text-sm font-black text-slate-800">{{ $model->name }}</span><span class="block truncate text-xs text-slate-400">{{ $model->provider->name }} · {{ $model->status === 'active' ? 'aktif' : 'nonaktif' }}</span></label>
                            <div class="w-20"><label class="mb-1 block text-[9px] font-bold uppercase text-slate-400">Urutan</label><input name="routes[{{ $feature->id }}][{{ $model->id }}][priority]" type="number" min="1" max="999" class="field min-h-9 px-2 py-1.5 text-center text-xs" value="{{ $priority }}" required></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
    @error('routes')<p class="field-error">{{ $message }}</p>@enderror
    <div class="sticky bottom-4 z-20 flex justify-end"><button class="btn-primary shadow-xl"><x-icon name="check" /> Simpan semua routing</button></div>
</form>
@endif
@endsection
