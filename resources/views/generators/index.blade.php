@extends('layouts.app', ['title' => 'Buat Perangkat'])

@section('content')
@php
    $designs = [
        'rpp' => ['wand', 'from-blue-500 to-brand-700', 'Rencana pembelajaran ringkas dan siap pakai.'],
        'modul_ajar' => ['file', 'from-violet-500 to-purple-700', 'Modul lengkap beserta asesmen dan refleksi.'],
        'silabus' => ['chart', 'from-cyan-500 to-blue-700', 'Pemetaan materi, kegiatan, dan asesmen.'],
        'lkpd' => ['edit', 'from-emerald-500 to-teal-700', 'Lembar aktivitas kontekstual untuk peserta didik.'],
        'soal' => ['check', 'from-amber-500 to-orange-600', 'Soal, kunci, pembahasan, dan kisi-kisi.'],
        'kisi_kisi' => ['grid', 'from-rose-500 to-pink-700', 'Indikator soal dan level kognitif terstruktur.'],
        'prota' => ['calendar', 'from-indigo-500 to-blue-700', 'Pembagian materi dan waktu selama satu tahun.'],
        'promes' => ['clock', 'from-fuchsia-500 to-violet-700', 'Rencana pembelajaran per minggu efektif.'],
        'atp' => ['arrow-right', 'from-teal-500 to-emerald-700', 'Alur tujuan pembelajaran bertahap.'],
        'bank_soal' => ['package', 'from-slate-600 to-slate-800', 'Kelola semua soal yang pernah dibuat.'],
    ];
@endphp

<section class="mb-7 overflow-hidden rounded-[1.7rem] bg-gradient-to-br from-[#17316d] via-[#1e4fb8] to-[#6946d7] p-6 text-white shadow-[0_24px_70px_-32px_rgba(30,64,175,.75)] sm:p-8">
    <div class="max-w-3xl"><span class="chip border border-white/20 bg-white/10 text-blue-100">✨ Studio perangkat pembelajaran</span><h1 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl">Mulai dari kebutuhan kelas Anda</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-blue-100 sm:text-base">Pilih jenis perangkat. Setiap formulir akan menyesuaikan data yang dibutuhkan, lalu menyimpan hasil, versi, dan kuotanya secara otomatis.</p></div>
</section>

@unless($quota['subscription'])
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800"><span>Paket Anda belum aktif. Draf dan dokumen lama tetap tersimpan; pembuatan baru membutuhkan paket aktif.</span><a href="{{ route('subscription.index') }}#pilih-paket" class="rounded-lg bg-amber-800 px-4 py-2 font-bold text-white">Pilih paket & upgrade</a></div>
@endunless

@if($drafts->isNotEmpty())
    <section class="surface mb-6 p-5 sm:p-6"><div class="mb-4"><p class="eyebrow">Pekerjaan tersimpan</p><h2 class="mt-1 text-xl font-black text-ink-950">Lanjutkan draf Anda</h2><p class="mt-1 text-xs text-slate-500">Draf formulir tersimpan pada akun Anda dan belum memakai kuota pembuatan.</p></div><div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@foreach($drafts as $draft)@php $draftFeature = $features->firstWhere('code', $draft->feature_code); $draftUrl = $draft->feature_code === 'rpp' ? route('rpp.create') : route('generator.create', $draft->feature_code); @endphp<a href="{{ $draftUrl }}" class="rounded-xl border border-brand-100 bg-brand-50/60 p-4 transition hover:border-brand-300 hover:bg-brand-50"><strong class="block text-sm text-brand-900">{{ $draftFeature?->name }}</strong><span class="mt-1 block truncate text-xs text-slate-600">{{ data_get($draft->payload_json, 'topic') ?: 'Materi belum diisi' }}</span><span class="mt-2 block text-[11px] font-bold text-brand-700">Disimpan {{ $draft->updated_at->locale('id')->diffForHumans() }} · Lanjutkan →</span></a>@endforeach</div></section>
@endif

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($features as $feature)
        @php
            [$icon, $gradient, $description] = $designs[$feature->code] ?? ['file', 'from-slate-500 to-slate-700', $feature->description];
            $usage = $quotaByCode->get($feature->code);
            $href = match($feature->code) {
                'rpp' => route('rpp.create'),
                'bank_soal' => route('questions.index'),
                default => route('generator.create', $feature->code),
            };
        @endphp
        @if($usage)
            <a href="{{ $href }}" class="surface group relative overflow-hidden p-5 transition duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-[0_24px_60px_-34px_rgba(30,92,232,.55)] sm:p-6">
                <div class="flex items-start justify-between gap-4"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br {{ $gradient }} text-white shadow-md"><x-icon :name="$icon" /></span>@if($feature->code !== 'bank_soal')<span class="chip bg-slate-50 text-slate-500">{{ $usage['remaining'] ?? '∞' }} tersisa</span>@else<span class="chip bg-emerald-50 text-emerald-700">KELOLA</span>@endif</div>
                <h2 class="mt-5 text-lg font-black text-ink-950">{{ $feature->name }}</h2><p class="mt-2 min-h-10 text-sm leading-5 text-slate-500">{{ $description }}</p>
                <span class="mt-5 inline-flex items-center gap-1.5 text-xs font-extrabold text-brand-700">{{ $feature->code === 'bank_soal' ? 'Buka koleksi' : ($draftsByCode->has($feature->code) ? 'Lanjutkan draf' : 'Mulai membuat') }} <x-icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1" /></span>
            </a>
        @else
            <div class="surface relative overflow-hidden p-5 opacity-60 sm:p-6">
                <div class="flex items-start justify-between gap-4"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br {{ $gradient }} text-white grayscale"><x-icon :name="$icon" /></span><span class="chip bg-slate-100 text-slate-500">TERKUNCI</span></div>
                <h2 class="mt-5 text-lg font-black text-ink-950">{{ $feature->name }}</h2><p class="mt-2 min-h-10 text-sm leading-5 text-slate-500">{{ $description }}</p><a href="{{ route('subscription.index') }}" class="mt-5 inline-flex items-center gap-1.5 text-xs font-extrabold text-slate-500">Lihat paket <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </div>
        @endif
    @endforeach
</div>
@endsection
