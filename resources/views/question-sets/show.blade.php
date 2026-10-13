@extends('layouts.app', ['title' => $set->title])

@section('content')
<style>
@media print {
    body { background: #fff !important; }
    body > div:first-child, aside, header, nav, .print-hidden { display: none !important; }
    body > div.min-h-screen { margin-left: 0 !important; padding-bottom: 0 !important; }
    main { margin: 0 !important; padding: 0 !important; max-width: none !important; }
    article { box-shadow: none !important; break-inside: auto; }
    article > p:first-child, article > p:nth-child(2) { break-after: avoid; }
    .print-answer { display: none !important; }
}
</style>
<div class="print-hidden mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><a href="{{ route('question-sets.index') }}" class="text-sm font-bold text-brand-700 hover:underline">← Semua Paket Soal</a><p class="mt-3 text-sm text-slate-500">{{ $set->questions->count() }} soal terpilih</p></div><button type="button" onclick="window.print()" class="btn-secondary">Cetak Soal</button></div>
<div class="mx-auto max-w-4xl">
    <div class="mb-6 border-b-2 border-slate-800 pb-4 text-center"><h1 class="text-2xl font-black text-ink-950">{{ $set->title }}</h1><p class="mt-1 text-sm text-slate-500">Paket soal RuangAjar</p></div>
    <div class="space-y-5">
        @foreach($set->questions as $question)
            <article class="surface p-5 sm:p-6"><p class="mb-2 text-xs font-bold text-slate-500">Nomor {{ $loop->iteration }} · {{ $question->subject?->name }} · {{ $question->grade?->name }} · {{ $question->topic }}</p><p class="font-semibold leading-7 text-slate-800">{{ $question->question_text }}</p>
                @if($question->image_asset)@include('documents.partials.question-image', ['image' => ['asset' => $question->image_asset, 'count' => $question->image_count]])@endif
                @if($question->options->isNotEmpty())<div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($question->options as $option)<p class="text-sm leading-6 text-slate-700"><strong>{{ $option->label }}.</strong> {{ $option->content }}</p>@endforeach</div>@else<div class="mt-5 h-16 border-b border-dashed border-slate-300"></div>@endif
                <div class="print-answer mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800"><strong>Kunci:</strong> {{ $question->answer_text }} @if($question->explanation)<p class="mt-2">{{ $question->explanation }}</p>@endif</div>
            </article>
        @endforeach
    </div>
</div>
@endsection
