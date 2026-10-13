@extends('layouts.app', ['title' => 'Bank Soal'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><p class="eyebrow">Koleksi pribadi</p><h1 class="mt-1 text-3xl font-black tracking-tight text-ink-950">Bank Soal</h1><p class="mt-2 text-sm text-slate-500">Edit, salin, dan pilih soal untuk disusun menjadi paket.</p></div>
    <div class="flex flex-wrap gap-2"><a href="{{ route('question-sets.index') }}" class="btn-secondary">Lihat Paket Soal</a><a href="{{ route('generator.create', 'soal') }}" class="btn-primary"><x-icon name="wand" /> Buat soal baru</a></div>
</div>

<form method="GET" class="surface mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
    <input name="search" value="{{ request('search') }}" class="field" placeholder="Cari materi atau isi soal..." aria-label="Cari soal">
    <select name="curriculum" class="field" aria-label="Filter kurikulum"><option value="">Semua kurikulum</option>@foreach($curriculums as $curriculum)<option value="{{ $curriculum->id }}" @selected(request('curriculum') == $curriculum->id)>{{ $curriculum->name }}</option>@endforeach</select>
    <select name="grade" class="field" aria-label="Filter kelas"><option value="">Semua kelas</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected(request('grade') == $grade->id)>{{ $grade->name }}</option>@endforeach</select>
    <select name="subject" class="field" aria-label="Filter mata pelajaran"><option value="">Semua mapel</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(request('subject') == $subject->id)>{{ $subject->name }}</option>@endforeach</select>
    <select name="difficulty" class="field" aria-label="Filter kesulitan"><option value="">Semua tingkat</option>@foreach(['mudah' => 'Mudah','sedang' => 'Sedang','sulit' => 'Sulit'] as $value => $label)<option value="{{ $value }}" @selected(request('difficulty') === $value)>{{ $label }}</option>@endforeach</select>
    <select name="type" class="field" aria-label="Filter bentuk soal"><option value="">Semua bentuk</option><option value="pilihan_ganda" @selected(request('type') === 'pilihan_ganda')>Pilihan ganda</option><option value="uraian" @selected(request('type') === 'uraian')>Uraian</option><option value="campuran" @selected(request('type') === 'campuran')>Campuran</option></select>
    <button class="btn-secondary">Terapkan Filter</button>
</form>

@if($errors->any())<div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-bold">Paket soal belum dapat dibuat.</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if($questions->isNotEmpty())
<form id="create-question-set" method="POST" action="{{ route('question-sets.store') }}" class="surface mb-5 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
    @csrf
    <div class="min-w-0 flex-1"><label for="set-title" class="mb-1 block text-sm font-bold text-slate-800">Buat paket dari soal terpilih</label><input id="set-title" name="title" value="{{ old('title') }}" class="field w-full" maxlength="255" placeholder="Contoh: Latihan Pecahan Kelas 4" required><p class="mt-1 text-xs text-slate-500">Pilih soal pada halaman ini. Urutannya mengikuti tampilan saat ini, maksimal 100 soal.</p></div>
    <button class="btn-primary sm:self-end">Buat Paket Soal</button>
</form>
@endif

<div class="space-y-4">
    @forelse($questions as $question)
        <article x-data="{ answer: false }" class="surface p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-2">
                <label class="mr-1 flex items-center gap-2 text-xs font-bold text-brand-700"><input type="checkbox" name="question_ids[]" value="{{ $question->id }}" form="create-question-set" class="h-4 w-4 accent-brand-600" @checked(in_array($question->id, old('question_ids', [])))> Pilih</label>
                <span class="chip bg-brand-50 text-brand-700">{{ $question->subject?->name }}</span><span class="chip bg-slate-100 text-slate-600">{{ $question->grade?->name }}</span><span class="chip bg-amber-50 text-amber-700">{{ ucfirst($question->difficulty) }}</span><span class="chip bg-violet-50 text-violet-700">{{ str($question->question_type)->replace('_', ' ')->title() }}</span><span class="ml-auto text-xs text-slate-400">{{ $question->created_at->diffForHumans() }}</span>
            </div>
            <p class="mt-3 text-xs font-semibold text-slate-500">{{ $question->topic }}</p><p class="mt-2 text-sm font-bold leading-6 text-slate-800">{{ $question->question_text }}</p>
            @if($question->image_asset)@include('documents.partials.question-image', ['image' => ['asset' => $question->image_asset, 'count' => $question->image_count]])@endif
            @if($question->options->isNotEmpty())<div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($question->options as $option)<div class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-600"><strong class="mr-2 text-slate-800">{{ $option->label }}.</strong>{{ $option->content }}</div>@endforeach</div>@endif
            <div x-show="answer" x-cloak class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm"><p class="font-extrabold text-emerald-800">Kunci: {{ $question->answer_text }}</p>@if($question->explanation)<p class="mt-2 leading-6 text-emerald-700">{{ $question->explanation }}</p>@endif</div>
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                <button type="button" @click="answer=!answer" class="btn-secondary min-h-9 px-3 py-1.5 text-xs"><x-icon name="check" class="h-4 w-4" /><span x-text="answer ? 'Sembunyikan kunci' : 'Lihat kunci'"></span></button>
                <a href="{{ route('questions.edit', $question) }}" class="btn-secondary min-h-9 px-3 py-1.5 text-xs">Edit</a>
                <form method="POST" action="{{ route('questions.duplicate', $question) }}">@csrf<button class="btn-secondary min-h-9 px-3 py-1.5 text-xs">Duplikasi</button></form>
                @if($question->document)<a href="{{ route('documents.show', $question->document) }}" class="btn-secondary min-h-9 px-3 py-1.5 text-xs"><x-icon name="file" class="h-4 w-4" /> Dokumen sumber</a>@endif
                <form method="POST" action="{{ route('questions.destroy', $question) }}" class="ml-auto" onsubmit="return confirm('Hapus soal ini dari Bank Soal dan paket yang memakainya?')">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-600 hover:text-rose-700">Hapus</button></form>
            </div>
        </article>
    @empty
        <div class="surface px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="package" /></span><h2 class="mt-4 text-lg font-black text-ink-950">Belum ada soal yang cocok</h2><p class="mt-2 text-sm text-slate-500">Buat soal baru atau ubah filter pencarian Anda.</p><a href="{{ route('generator.create', 'soal') }}" class="btn-primary mt-5">Buat soal</a></div>
    @endforelse
</div>

<div class="mt-6">{{ $questions->links() }}</div>
@endsection
