@extends('layouts.app', ['title' => $document->title])

@section('content')
@php
    $isRpp = $document->feature->code === 'rpp';
    $aiFailureDraft = $document->status === 'draft' && data_get($document->metadata_json, 'source') === 'ai-unavailable';
    $content = $isRpp
        ? \App\Services\Documents\DocumentContentFormatter::normalizeRpp($document->content_json ?? [])
        : ($document->content_json ?? []);
    $earlyReader = in_array($document->feature->code, ['soal', 'lkpd'], true) && (int) $document->grade?->numeric_value <= 3 && data_get($document->metadata_json, 'settings.reading_ability') === 'belum_lancar';
    $rppEditable = [
        'objectives' => ['Tujuan Pembelajaran', $content['objectives'] ?? []],
        'opening' => ['Kegiatan Pendahuluan', $content['opening'] ?? []],
        'main_activity' => ['Kegiatan Inti', $content['main_activity'] ?? []],
        'closing' => ['Kegiatan Penutup', $content['closing'] ?? []],
    ];
@endphp
<div x-data="{ editing: null, duplicating: false }">
    @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    <div class="mb-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div><a href="{{ route('documents.index') }}" class="mb-3 inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-brand-700"><x-icon name="arrow-left" class="h-4 w-4" /> Dokumen saya</a><div class="flex flex-wrap items-center gap-2"><span class="chip bg-brand-50 text-brand-700">{{ $document->feature->name }}</span><span class="chip bg-emerald-50 text-emerald-700">{{ ['generated' => 'Siap', 'edited' => 'Diedit', 'draft' => 'Draft', 'archived' => 'Diarsipkan'][$document->status] ?? ucfirst($document->status) }}</span><span class="text-xs text-slate-400">Versi {{ $document->versions->max('version_number') }}</span></div><h1 class="mt-3 max-w-4xl text-2xl font-black tracking-tight text-ink-950 sm:text-3xl">{{ $document->title }}</h1><p class="mt-2 text-sm text-slate-500">Terakhir diperbarui {{ $document->updated_at->locale('id')->diffForHumans() }}</p></div>
        <div class="flex flex-wrap gap-2">@if($isRpp && ! $aiFailureDraft && auth()->user()->can('update', $document))<button type="button" @click="duplicating=true" class="btn-secondary">Salin RPP</button>@endif @unless($aiFailureDraft)<a href="{{ route('documents.print', $document) }}" target="_blank" rel="noopener" class="btn-secondary"><x-icon name="file" /> Cetak semua halaman</a><a href="{{ route('documents.pdf', $document) }}" class="btn-primary"><x-icon name="download" /> Unduh PDF</a><form method="POST" action="{{ route('documents.exports.store', $document) }}">@csrf<button class="btn-secondary" type="submit">Siapkan PDF besar di latar belakang</button></form>@endunless</div>
    </div>
    @if($latestExport)
        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            @if($latestExport->status === 'completed' && $latestExport->expires_at?->isFuture() && $latestExport->content_hash === hash('sha256', json_encode($document->content_json, JSON_THROW_ON_ERROR)))
                PDF latar belakang siap. <a class="font-bold text-brand-700 underline" href="{{ route('documents.exports.download', $latestExport) }}">Unduh hasil</a> (tersedia sampai {{ $latestExport->expires_at->locale('id')->translatedFormat('d F Y H:i') }}).
            @elseif(in_array($latestExport->status, ['queued', 'running'], true))
                PDF sedang disiapkan. Segarkan halaman ini beberapa saat lagi.
            @else
                PDF sebelumnya tidak tersedia atau dokumen sudah berubah. Siapkan ulang jika diperlukan.
            @endif
        </div>
    @endif
    @if($aiFailureDraft)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-extrabold">Dokumen ini masih draf karena AI belum tersedia.</p>
            <p class="mt-1">Isian Anda tetap tersimpan. Lanjutkan dari formulir untuk mencoba lagi saat model AI kembali tersedia; kuota belum digunakan.</p>
            <a href="{{ $isRpp ? route('rpp.create') : route('generator.create', $document->feature->code) }}" class="btn-secondary mt-3">Lanjutkan draf formulir</a>
        </div>
    @endif
    @if(data_get($document->metadata_json, 'source') === 'preset-demo')
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            <p class="font-extrabold">Konten contoh perlu ditinjau sebelum dibagikan.</p>
            <p class="mt-1">Dokumen ini dibuat dari pola umum tanpa hasil AI. Periksa kecocokan dengan CP/KD, jenjang, ketepatan bahasa, variasi soal atau aktivitas, dan sumber rujukan. PDF akan diberi penanda “Contoh - perlu ditinjau”.</p>
        </div>
    @endif
    @if($document->sourceDocument || ($nextCodes !== [] && auth()->user()->can('update', $document)))
        <div class="mb-6 flex flex-col gap-4 rounded-2xl border border-brand-200 bg-brand-50/70 p-4 sm:p-5">
            @if($document->sourceDocument)<p class="text-xs text-brand-800"><span class="font-extrabold">Dibuat dari:</span> <a href="{{ route('documents.show', $document->sourceDocument) }}" class="underline hover:text-brand-950">{{ $document->sourceDocument->feature?->name }} · {{ $document->sourceDocument->title }}</a></p>@endif
            @if($nextCodes !== [] && auth()->user()->can('update', $document))
                <div><p class="text-sm font-extrabold text-ink-950">Lanjutkan perangkat ini</p><p class="mt-1 text-xs text-slate-600">Identitas dan konten yang relevan akan dibawa ke dokumen berikutnya.</p><div class="mt-3 flex flex-wrap gap-2">@foreach($nextCodes as $nextCode)<a href="{{ $nextCode === 'rpp' ? route('rpp.create', ['source' => $document->uuid]) : route('generator.create', ['featureCode' => $nextCode, 'source' => $document->uuid]) }}" class="btn-secondary min-h-9 px-3 py-1.5 text-xs">Buat {{ ['promes' => 'Promes', 'atp' => 'ATP', 'rpp' => 'RPP', 'modul_ajar' => 'Modul Ajar', 'lkpd' => 'LKPD', 'soal' => 'Soal', 'kisi_kisi' => 'Kisi-Kisi'][$nextCode] }}</a>@endforeach</div></div>
            @endif
        </div>
    @endif
    @if($isRpp && auth()->user()->can('update', $document))
        <div x-show="duplicating" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @keydown.escape.window="duplicating=false">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.outside="duplicating=false">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-black text-ink-950">Salin RPP</h2><p class="mt-1 text-sm text-slate-500">Isi lama disalin agar bisa diedit. Menyalin tidak memakai kuota AI.</p></div><button type="button" @click="duplicating=false" class="text-slate-500">Tutup</button></div>
                <form method="POST" action="{{ route('documents.duplicate', $document) }}" class="mt-5 space-y-4">@csrf
                    <label class="block text-sm font-bold">Judul baru<input name="title" required maxlength="255" value="{{ old('title', 'Salinan: '.$document->title) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="block text-sm font-bold">Materi<input name="topic" required minlength="3" maxlength="180" value="{{ old('topic', $document->topic) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="block text-sm font-bold">Submateri<input name="subtopic" maxlength="180" value="{{ old('subtopic', $document->subtopic) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <div class="grid grid-cols-2 gap-3"><label class="block text-sm font-bold">Pertemuan<input name="meetings" type="number" min="1" max="20" required value="{{ old('meetings', data_get($document->metadata_json, 'meetings', 1)) }}" class="mt-1 w-full rounded-xl border-slate-300"></label><label class="block text-sm font-bold">Tanggal belajar<input name="lesson_date" type="date" value="{{ old('lesson_date', data_get($document->metadata_json, 'lesson_date')) }}" class="mt-1 w-full rounded-xl border-slate-300"></label></div>
                    <p class="text-xs text-amber-700">Tinjau kembali tujuan, kegiatan, dan asesmen setelah mengganti materi.</p>
                    <button class="btn-primary w-full justify-center">Buat salinan</button>
                </form>
            </div>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_280px]">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_20px_70px_-38px_rgba(15,23,42,.35)]">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-center text-[10px] font-extrabold uppercase tracking-[.2em] text-slate-400">Pratinjau dokumen · PDF otomatis berlanjut ke halaman A4 berikutnya</div>
            <div class="mx-auto max-w-[850px] px-5 py-8 text-slate-800 sm:px-10 sm:py-12 lg:px-16">
                <header class="mb-8 border-b-4 border-double border-slate-800 pb-5 text-center"><p class="text-xs font-bold uppercase tracking-widest">{{ $document->school?->foundation_name }}</p><h2 class="mt-1 text-xl font-black uppercase tracking-wide">{{ $document->school?->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $document->school?->address }}, {{ $document->school?->city }} · {{ $document->school?->email }}</p></header>
                <h2 class="text-center text-lg font-black uppercase tracking-wider">{{ $isRpp ? 'Rencana Pelaksanaan Pembelajaran' : $document->feature->name }}</h2>
                <table class="mt-7 w-full text-sm">
                    <tbody>
                        @foreach(['Sekolah' => data_get($content,'identity.school'),'Konsentrasi Keahlian' => $document->vocationalProgram?->name,'Mata Pelajaran' => data_get($content,'identity.subject'),'Kelas / Semester' => data_get($content,'identity.class').' / '.data_get($content,'identity.semester'),'Materi' => $document->topic,'Tahun Pelajaran' => data_get($content,'identity.academic_year'),'Alokasi Waktu' => data_get($content,'identity.time_allocation')] as $label => $value)
                            @if(filled($value))
                                <tr><td class="w-40 py-1 align-top font-bold">{{ $label }}</td><td class="w-4 py-1 align-top">:</td><td class="py-1">{{ $value }}</td></tr>
                            @endif
                        @endforeach
                        @if(data_get($content, 'identity.lesson_date'))
                            <tr><td class="w-40 py-1 align-top font-bold">Tanggal Belajar</td><td class="w-4 py-1 align-top">:</td><td class="py-1">{{ \Illuminate\Support\Carbon::parse(data_get($content, 'identity.lesson_date'))->locale('id')->translatedFormat('d F Y') }}</td></tr>
                        @endif
                    </tbody>
                </table>

                @if($isRpp)
                    @foreach($rppEditable as $key => [$title,$items])
                        <section class="group relative mt-8 rounded-xl border border-transparent p-3 transition hover:border-brand-100 hover:bg-brand-50/20">
                            <div class="mb-3 flex items-center justify-between gap-3"><h3 class="font-black text-slate-900">{{ chr(64 + $loop->iteration) }}. {{ $title }}</h3><div class="no-print flex shrink-0 gap-2">@if(in_array($key, $regeneratableKeys, true))<form method="POST" action="{{ route('documents.regenerate', $document) }}" onsubmit="return confirm('Buat ulang bagian ini dengan AI? Kuota pembuatan ulang akan digunakan jika berhasil.')">@csrf<input type="hidden" name="section" value="{{ $key }}"><button class="rounded-lg bg-brand-50 px-2.5 py-1.5 text-[11px] font-bold text-brand-700">Buat ulang</button></form>@endif<button @click="editing='{{ $key }}'" class="rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-bold text-brand-700 ring-1 ring-brand-100"><x-icon name="edit" class="h-3.5 w-3.5" /> Edit</button></div></div>
                            @if($key === 'main_activity')
                                <div class="space-y-5">@foreach(\App\Services\Documents\DocumentContentFormatter::activityGroups($document->content_json['main_activity'] ?? []) as $group)<div class="break-inside-avoid-page">@if(filled($group['title']))<h4 class="mb-2 text-sm font-extrabold text-brand-800">{{ $group['title'] }}</h4>@endif<ol class="list-decimal space-y-2 pl-5 text-sm leading-6">@foreach($group['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol></div>@endforeach</div>
                            @else
                                <ol class="space-y-2 pl-5 text-sm leading-6 {{ $key === 'objectives' ? 'list-decimal' : 'list-disc' }}">@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ol>
                            @endif
                        </section>
                        @include('documents.partials.edit-modal', ['key' => $key, 'title' => $title, 'value' => implode("\n", $items)])
                    @endforeach
                    <section class="mt-8 p-3"><div class="mb-3 flex items-center justify-between gap-3"><h3 class="font-black text-slate-900">E. Asesmen</h3>@if(in_array('assessment', $regeneratableKeys, true))<form class="no-print" method="POST" action="{{ route('documents.regenerate', $document) }}" onsubmit="return confirm('Buat ulang asesmen dengan AI? Kuota akan digunakan jika berhasil.')">@csrf<input type="hidden" name="section" value="assessment"><button class="rounded-lg bg-brand-50 px-2.5 py-1.5 text-[11px] font-bold text-brand-700">Buat ulang</button></form>@endif</div><div class="overflow-x-auto"><table class="w-full border-collapse text-xs"><thead><tr class="bg-slate-100"><th class="border border-slate-300 p-2 text-left">Jenis</th><th class="border border-slate-300 p-2 text-left">Teknik / Instrumen</th></tr></thead><tbody>@foreach($content['assessment'] ?? [] as $item)<tr><td class="border border-slate-300 p-2 font-bold">{{ $item['type'] ?? '' }}</td><td class="border border-slate-300 p-2">{{ $item['method'] ?? '' }}</td></tr>@endforeach</tbody></table></div></section>
                    <section class="mt-8 grid gap-4 p-3 sm:grid-cols-2"><div><h3 class="font-black text-slate-900">F. Remedial</h3><p class="mt-2 text-sm leading-6">{{ data_get($content,'differentiation.remedial') }}</p></div><div><h3 class="font-black text-slate-900">G. Pengayaan</h3><p class="mt-2 text-sm leading-6">{{ data_get($content,'differentiation.enrichment') }}</p></div></section>
                @else
                    @foreach($content['sections'] ?? [] as $section)
                        @php $editable = in_array($section['type'] ?? '', ['text','list'], true); @endphp
                        <section class="group relative mt-8 rounded-xl border border-transparent p-3 transition hover:border-brand-100 hover:bg-brand-50/20">
                            <div class="mb-3 flex items-center justify-between gap-3"><h3 class="font-black text-slate-900">{{ $loop->iteration }}. {{ $section['title'] }}</h3>@if($editable)<div class="no-print flex shrink-0 gap-2">@if(in_array($section['key'], $regeneratableKeys, true))<form method="POST" action="{{ route('documents.regenerate', $document) }}" onsubmit="return confirm('Buat ulang bagian ini dengan AI? Kuota akan digunakan jika berhasil.')">@csrf<input type="hidden" name="section" value="{{ $section['key'] }}"><button class="rounded-lg bg-brand-50 px-2.5 py-1.5 text-[11px] font-bold text-brand-700">Buat ulang</button></form>@endif<button @click="editing='{{ $section['key'] }}'" class="rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-bold text-brand-700 ring-1 ring-brand-100"><x-icon name="edit" class="h-3.5 w-3.5" /> Edit</button></div>@endif</div>
                            @if($section['type'] === 'text')
                                @foreach(preg_split('/\r\n|\r|\n/', (string) $section['content']) as $paragraph)<p class="mb-2 {{ $earlyReader ? 'text-lg leading-9' : 'text-sm leading-7' }}">{{ $paragraph }}</p>@endforeach
                            @elseif($section['type'] === 'list')
                                <ol class="list-decimal space-y-2 pl-5 {{ $earlyReader ? 'text-lg leading-9' : 'text-sm leading-6' }}">@foreach(\App\Services\Documents\DocumentContentFormatter::list($section['content'] ?? []) as $item)<li>{{ $item }}</li>@endforeach</ol>
                            @elseif($section['type'] === 'table')
                                <div class="overflow-x-auto"><table class="w-full min-w-[620px] border-collapse text-xs"><thead><tr class="bg-slate-100">@foreach($section['headers'] ?? [] as $header)<th class="border border-slate-300 p-2 text-left">{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach($section['content'] as $row)<tr>@foreach($row as $cell)<td class="border border-slate-300 p-2 align-top">{{ \App\Services\Documents\DocumentContentFormatter::text($cell) }}</td>@endforeach</tr>@endforeach</tbody></table></div>
                            @elseif($section['type'] === 'questions')
                                <div class="space-y-6">@foreach($section['content'] as $question)<div class="break-inside-avoid"><p class="{{ $earlyReader ? 'text-lg leading-9' : 'text-sm leading-6' }} font-bold">{{ $question['number'] }}. {{ $question['question'] }}</p>@if(!empty($question['image']))@include('documents.partials.question-image', ['image' => $question['image']])@endif@if(!empty($question['options']))<div class="mt-2 grid gap-1.5 pl-5 {{ $earlyReader ? 'text-lg leading-9' : 'text-sm' }} sm:grid-cols-2">@foreach($question['options'] as $option)<p><strong>{{ $option['label'] }}.</strong> {{ $option['content'] }}</p>@endforeach</div>@endif@if(data_get($document->metadata_json, 'settings.include_answer', true))<details class="no-print mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800"><summary class="cursor-pointer font-extrabold">Kunci{{ data_get($document->metadata_json, 'settings.include_explanation', true) ? ' & pembahasan' : '' }}</summary><p class="mt-2"><strong>{{ $question['answer'] }}</strong>@if(data_get($document->metadata_json, 'settings.include_explanation', true)) — {{ $question['explanation'] }}@endif</p></details>@endif</div>@endforeach</div>
                            @endif
                        </section>
                        @if($editable)@include('documents.partials.edit-modal', ['key' => $section['key'], 'title' => $section['title'], 'value' => ($section['type'] ?? '') === 'list' ? implode("\n", \App\Services\Documents\DocumentContentFormatter::list($section['content'] ?? [])) : \App\Services\Documents\DocumentContentFormatter::text($section['content'] ?? '')])@endif
                    @endforeach
                @endif

                <footer class="mt-14 grid grid-cols-2 gap-10 text-center text-xs"><div><p>Mengetahui,<br>Kepala Sekolah</p><div class="h-16"></div><p class="font-bold underline">{{ $document->school?->principal_name ?: '..............................' }}</p><p>NIP. {{ $document->school?->principal_identifier ?: '..............................' }}</p></div><div><p>{{ $document->school?->city }}, {{ $document->created_at->locale('id')->translatedFormat('d F Y') }}<br>Guru Mata Pelajaran</p><div class="h-16"></div><p class="font-bold underline">{{ $document->user->name }}</p><p>NIP. ..............................</p></div></footer>
            </div>
        </article>

        <aside class="space-y-4"><div class="surface p-5"><h3 class="font-black text-ink-950">Detail dokumen</h3><dl class="mt-4 space-y-3 text-xs"><div class="flex justify-between gap-3"><dt class="text-slate-400">Kurikulum</dt><dd class="text-right font-bold text-slate-700">{{ $document->curriculum?->name }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-400">Jenjang</dt><dd class="font-bold text-slate-700">{{ $document->educationLevel?->code }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-400">Kelas</dt><dd class="font-bold text-slate-700">{{ $document->grade?->name }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-400">Sumber</dt><dd class="font-bold text-slate-700">{{ data_get($document->metadata_json, 'source') === 'hybrid-ai' ? 'Preset + AI' : (data_get($document->metadata_json, 'source') === 'duplicated' ? 'Salinan RPP' : 'Preset terstruktur') }}</dd></div>@if($quota)<div class="flex justify-between gap-3"><dt class="text-slate-400">Sisa buat ulang AI</dt><dd class="font-bold text-slate-700">{{ $quota['regeneration_remaining'] ?? 'Tanpa batas' }}</dd></div>@endif</dl>@if($document->feature->code === 'soal')<a href="{{ route('questions.index') }}" class="mt-5 inline-flex items-center gap-1 text-xs font-extrabold text-brand-700">Buka Bank Soal <x-icon name="arrow-right" class="h-4 w-4" /></a>@endif</div><div class="surface p-5"><div class="flex items-center justify-between"><h3 class="font-black text-ink-950">Riwayat versi</h3><span class="chip bg-slate-100 text-slate-500">{{ $document->versions->count() }}</span></div><div class="mt-4 space-y-3">@foreach($document->versions->take(5) as $version)<div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-lg {{ $loop->first ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500' }} text-xs font-black">v{{ $version->version_number }}</span><div><p class="text-xs font-bold text-slate-700">{{ $version->version_number === 1 ? 'Dibuat' : 'Diedit' }}</p><p class="text-[10px] text-slate-400">{{ $version->created_at->diffForHumans() }}</p></div></div>@endforeach</div></div><form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Hapus dokumen ini?')">@csrf @method('DELETE')<button class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-extrabold text-rose-600 hover:bg-rose-100"><x-icon name="trash" class="h-4 w-4" /> Hapus dokumen</button></form></aside>
    </div>
</div>
@endsection
