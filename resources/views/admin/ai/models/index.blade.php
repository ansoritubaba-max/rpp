@extends('layouts.app', ['title' => 'Model AI'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">AI control center</p><h1 class="mt-1 text-3xl font-black text-ink-950">Model AI</h1><p class="mt-2 text-sm text-slate-500">Atur model, harga token, kuota harian, dan cooldown. Tarif Google di bawah adalah perkiraan tier berbayar; free tier dapat berlaku sesuai proyek dan batas kuota Google.</p></div><a href="{{ route('admin.ai.models.create') }}" class="btn-primary"><x-icon name="plus" /> Tambah model</a></div>

@include('admin.ai._nav')

<div class="surface overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1000px] text-left text-sm">
            <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-4">Model</th><th class="px-4 py-4">Provider</th><th class="px-4 py-4">Health</th><th class="px-4 py-4">Routing</th><th class="px-4 py-4">Limit / hari</th><th class="px-4 py-4">Biaya / 1 jt token</th><th class="px-5 py-4 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($models as $model)
                    @php $cooling = $model->health?->cooldown_until?->isFuture() ?? false; @endphp
                    <tr class="hover:bg-brand-50/20">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl {{ $model->status === 'active' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-400' }}"><x-icon name="sparkles" /></span>
                                <div>
                                    <p class="font-black text-slate-800">{{ $model->name }}</p>
                                    <p class="mt-0.5 font-mono text-[11px] text-slate-400">{{ $model->model_key }}</p>
                                    @if($model->provider->provider_type === 'google' && str_contains($model->model_key, 'flash-lite'))
                                        <p class="mt-1 text-[11px] font-semibold text-emerald-700">Free tier tersedia · kuota Google berlaku</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <p class="font-bold text-slate-700">{{ $model->provider->name }}</p>
                            <p class="text-xs text-slate-400">Prioritas default {{ $model->priority }}</p>
                        </td>
                        <td class="px-4 py-4">
                            @if($model->status !== 'active')
                                <span class="chip bg-slate-100 text-slate-500">Nonaktif</span>
                            @elseif($cooling)
                                <span class="chip bg-rose-50 text-rose-700">Cooldown</span>
                            @elseif($model->health?->status === 'healthy' && $model->health?->last_success_at)
                                <span class="chip bg-emerald-50 text-emerald-700">Sehat</span>
                            @else
                                <span class="chip bg-amber-50 text-amber-700">Belum pernah berhasil</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 font-bold text-slate-700">{{ $model->routes_count }} fitur</td>
                        <td class="px-4 py-4 text-slate-600">{{ $model->internal_daily_limit ? number_format($model->internal_daily_limit) : 'Tanpa batas' }}</td>
                        <td class="px-4 py-4 text-xs text-slate-600">
                            @if($model->estimated_input_cost === null || $model->estimated_output_cost === null)
                                <span class="text-slate-400">Belum diisi</span>
                            @else
                                <span class="font-bold">${{ number_format((float) $model->estimated_input_cost, 4) }}</span> masuk<br>
                                <span class="font-bold">${{ number_format((float) $model->estimated_output_cost, 4) }}</span> keluar
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.ai.models.edit', $model) }}" class="inline-flex rounded-lg border border-slate-200 p-2 text-slate-500 transition hover:border-brand-200 hover:text-brand-700" aria-label="Edit {{ $model->name }}"><x-icon name="edit" class="h-4 w-4" /></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-16 text-center"><h2 class="font-black text-ink-950">Belum ada model AI</h2><p class="mt-2 text-sm text-slate-400">Tambahkan provider terlebih dahulu, lalu daftarkan modelnya.</p><a href="{{ route('admin.ai.models.create') }}" class="btn-primary mt-5">Tambah model</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
