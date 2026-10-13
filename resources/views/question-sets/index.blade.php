@extends('layouts.app', ['title' => 'Paket Soal'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Koleksi pribadi</p><h1 class="mt-1 text-3xl font-black text-ink-950">Paket Soal</h1><p class="mt-2 text-sm text-slate-500">Kumpulan soal pilihan Anda, tanpa mengurangi kuota generate.</p></div><a href="{{ route('questions.index') }}" class="btn-primary">Pilih Soal dari Bank</a></div>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse($sets as $set)
        <article class="surface p-5"><p class="text-xs font-bold uppercase tracking-wider text-brand-700">{{ $set->questions_count }} soal</p><h2 class="mt-2 text-lg font-black text-ink-950">{{ $set->title }}</h2><p class="mt-2 text-xs text-slate-500">Dibuat {{ $set->created_at->locale('id')->translatedFormat('d F Y') }}</p><div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4"><a href="{{ route('question-sets.show', $set) }}" class="btn-secondary">Buka paket</a><form method="POST" action="{{ route('question-sets.destroy', $set) }}" onsubmit="return confirm('Hapus paket ini? Soal aslinya tetap tersimpan.')">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-600 hover:text-rose-700">Hapus</button></form></div></article>
    @empty
        <div class="surface p-10 text-center md:col-span-2 xl:col-span-3"><h2 class="text-lg font-black text-ink-950">Belum ada paket soal</h2><p class="mt-2 text-sm text-slate-500">Pilih beberapa soal dari Bank Soal untuk membuat paket pertama.</p><a href="{{ route('questions.index') }}" class="btn-primary mt-5">Buka Bank Soal</a></div>
    @endforelse
</div>
<div class="mt-6">{{ $sets->links() }}</div>
@endsection
