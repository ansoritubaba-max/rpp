@extends('layouts.app', ['title' => 'Edit Soal'])

@section('content')
<div class="mb-6"><a href="{{ route('questions.index') }}" class="text-sm font-bold text-brand-700 hover:underline">← Kembali ke Bank Soal</a><h1 class="mt-3 text-3xl font-black text-ink-950">Edit Soal</h1><p class="mt-2 text-sm text-slate-500">Perubahan di Bank Soal tidak mengubah dokumen sumber yang sudah dibuat.</p></div>
<form method="POST" action="{{ route('questions.update', $question) }}" class="surface max-w-3xl space-y-5 p-5 sm:p-7">
    @csrf @method('PATCH')
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"><p class="font-bold">Periksa kembali isian soal.</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label for="topic" class="mb-1 block text-sm font-bold text-slate-700">Materi</label><input id="topic" name="topic" class="field w-full" value="{{ old('topic', $question->topic) }}" required maxlength="255"></div>
        <div><label for="subtopic" class="mb-1 block text-sm font-bold text-slate-700">Submateri</label><input id="subtopic" name="subtopic" class="field w-full" value="{{ old('subtopic', $question->subtopic) }}" maxlength="255"></div>
        <div><label for="difficulty" class="mb-1 block text-sm font-bold text-slate-700">Kesulitan</label><select id="difficulty" name="difficulty" class="field w-full">@foreach(['mudah' => 'Mudah', 'sedang' => 'Sedang', 'sulit' => 'Sulit'] as $value => $label)<option value="{{ $value }}" @selected(old('difficulty', $question->difficulty) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="cognitive_level" class="mb-1 block text-sm font-bold text-slate-700">Level kognitif</label><input id="cognitive_level" name="cognitive_level" class="field w-full" value="{{ old('cognitive_level', $question->cognitive_level) }}" maxlength="30" placeholder="Contoh: C2"></div>
    </div>
    <div><label for="question_text" class="mb-1 block text-sm font-bold text-slate-700">Isi soal</label><textarea id="question_text" name="question_text" class="field min-h-32 w-full" required>{{ old('question_text', $question->question_text) }}</textarea></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label for="image_asset" class="mb-1 block text-sm font-bold text-slate-700">Gambar (opsional)</label><select id="image_asset" name="image_asset" class="field"><option value="">Tanpa gambar</option>@foreach(app(\App\Services\Documents\QuestionAssetLibrary::class)->all() as $key => $asset)<option value="{{ $key }}" @selected(old('image_asset', $question->image_asset) === $key)>{{ $asset['category'] }} · {{ $asset['label'] }}</option>@endforeach</select></div>
        <div><label for="image_count" class="mb-1 block text-sm font-bold text-slate-700">Jumlah gambar</label><input id="image_count" name="image_count" type="number" min="1" max="10" value="{{ old('image_count', $question->image_count ?? 3) }}" class="field"></div>
    </div>
    @if($question->options->isNotEmpty())
        <fieldset class="space-y-3"><legend class="text-sm font-bold text-slate-700">Pilihan dan jawaban benar</legend>
            @foreach($question->options as $option)<div class="flex items-start gap-3"><label class="mt-3 flex shrink-0 items-center gap-2 text-sm font-bold text-slate-700"><input type="radio" name="correct_option" value="{{ $option->id }}" class="accent-brand-600" @checked(old('correct_option', $question->options->firstWhere('is_correct', true)?->id) == $option->id) required>{{ $option->label }}</label><textarea name="options[{{ $option->id }}]" class="field min-h-20 w-full" aria-label="Isi pilihan {{ $option->label }}" required>{{ old('options.'.$option->id, $option->content) }}</textarea></div>@endforeach
        </fieldset>
    @else
        <div><label for="answer_text" class="mb-1 block text-sm font-bold text-slate-700">Kunci jawaban</label><textarea id="answer_text" name="answer_text" class="field min-h-24 w-full" required>{{ old('answer_text', $question->answer_text) }}</textarea></div>
    @endif
    <div><label for="explanation" class="mb-1 block text-sm font-bold text-slate-700">Pembahasan</label><textarea id="explanation" name="explanation" class="field min-h-28 w-full">{{ old('explanation', $question->explanation) }}</textarea></div>
    <div class="flex flex-wrap justify-end gap-2"><a href="{{ route('questions.index') }}" class="btn-secondary">Batal</a><button class="btn-primary">Simpan Perubahan</button></div>
</form>
@endsection
