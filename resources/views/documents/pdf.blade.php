<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @php
        $fontFamily = $template?->font_family ?? 'DejaVu Sans';
        $fontSize = (float) ($template?->font_size ?? 10);
        $lineHeight = (float) ($template?->line_height ?? 1.5);
        $earlyReader = in_array($document->feature->code, ['soal', 'lkpd'], true) && (int) $document->grade?->numeric_value <= 3 && data_get($document->metadata_json, 'settings.reading_ability') === 'belum_lancar';
        if ($earlyReader) {
            $fontSize = max(13, $fontSize);
            $lineHeight = max(1.6, $lineHeight);
        }
        $marginTop = (float) ($template?->margin_top ?? $systemSettings['margin_top']);
        $marginRight = (float) ($template?->margin_right ?? $systemSettings['margin_right']);
        $marginBottom = (float) ($template?->margin_bottom ?? $systemSettings['margin_bottom']);
        $marginLeft = (float) ($template?->margin_left ?? $systemSettings['margin_left']);
        $autoLandscape = ($orientation ?? 'portrait') === 'landscape' && ($template?->orientation ?? 'portrait') !== 'landscape';
        if ($autoLandscape) {
            $fontSize = min(9, $fontSize);
            $lineHeight = min(1.35, $lineHeight);
            $marginTop = min(12, $marginTop);
            $marginRight = min(12, $marginRight);
            $marginBottom = min(12, $marginBottom);
            $marginLeft = min(12, $marginLeft);
        }
        $content = $document->content_json;
        $isQuestionPaper = $document->feature->code === 'soal';
        $isWorksheet = $document->feature->code === 'lkpd';
        $includeAnswer = (bool) data_get($document->metadata_json, 'settings.include_answer', true);
        $includeExplanation = (bool) data_get($document->metadata_json, 'settings.include_explanation', true);
        $questionItems = collect($renderSections)->where('type', 'questions')->flatMap(fn ($section) => $section['content'] ?? [])->values();
        $teacherSections = $isQuestionPaper ? collect($renderSections)->whereIn('key', ['kisi_kisi_ringkas'])->values() : collect();
        $curriculumSources = collect(data_get($document->metadata_json, 'curriculum_sources', []))
            ->filter(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL))
            ->unique()->values();
    @endphp
    <style>
        @page { margin: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom + 6 }}mm {{ $marginLeft }}mm; }
        body { font-family: "{{ $fontFamily }}", sans-serif; color: #111; font-size: {{ $fontSize }}pt; line-height: {{ $lineHeight }}; }
        header { text-align: center; border-bottom: 3px double #111; padding-bottom: 8px; margin-bottom: {{ $autoLandscape ? 10 : 18 }}px; }
        .letterhead td { vertical-align: middle; }
        .letterhead .logo { width: 20mm; text-align: center; }
        .letterhead .logo img { display: block; max-width: 18mm; max-height: 18mm; margin: 0 auto; }
        .letterhead .school-title { text-align: center; }
        .letterhead .school-title h1 { margin: 1px 0 3px; }
        h1 { font-size: {{ $fontSize + 4 }}pt; text-transform: uppercase; margin: 0 0 4px; }
        h2 { font-size: {{ $fontSize + 1 }}pt; margin: 12px 0 5px; page-break-after: avoid; }
        p { margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; }
        .identity td { padding: 2px 0; vertical-align: top; }
        .identity td:first-child { width: 130px; font-weight: bold; }
        .border td, .border th { border: 1px solid #555; padding: 6px; text-align: left; vertical-align: top; }
        .border th { background: #eee; }
        .wide-table { table-layout: fixed; font-size: {{ max(8, $fontSize - 1) }}pt; }
        .wide-table td, .wide-table th { word-wrap: break-word; overflow-wrap: break-word; }
        ol, ul { margin: 5px 0 5px 20px; padding: 0; }
        li { margin-bottom: 1px; }
        .activity-group { margin: 9px 0 13px; }
        .activity-group .activity-title { display: block; font-size: {{ $fontSize }}pt; margin: 0 0 7px; page-break-after: avoid; }
        .activity-group ol { display: block; clear: both; margin-top: 5px; }
        .activity-group li { margin-bottom: 5px; }
        .signatures { margin-top: 8px; page-break-inside: avoid; }
        .signatures td { text-align: center; width: 50%; vertical-align: top; }
        .space { height: 24px; }
        .muted { color: #444; font-size: {{ max(7, $fontSize - 1.5) }}pt; }
        .question { page-break-inside: avoid; margin-bottom: 14px; }
        .options { margin: 4px 0 0 18px; }
        .answer { font-size: {{ max(7, $fontSize - 1.5) }}pt; color: #333; margin-left: 18px; }
        .student-fields { margin: 14px 0 18px; }
        .student-fields td { width: 50%; padding: 4px 12px 4px 0; }
        .worksheet-answer tbody td { height: 22mm; }
        .worksheet-answer tbody td:last-child { width: 70%; }
        .worksheet-results { page-break-before: always; }
        .teacher-appendix { page-break-before: always; }
        .teacher-appendix h2 { text-align: center; text-transform: uppercase; margin-bottom: 12px; }
        .answer-table { font-size: {{ max(8, $fontSize - 1.5) }}pt; line-height: 1.3; }
        .answer-table td, .answer-table th { padding: 4px 6px; }
        .answer-table td:first-child { width: 8%; }
        .references { margin-top: 16px; font-size: {{ max(7, $fontSize - 1.5) }}pt; page-break-inside: avoid; }
        .references li { overflow-wrap: anywhere; word-wrap: break-word; }
        footer { position: fixed; bottom: -18mm; left: 0; right: 0; text-align: center; color: #777; font-size: 7pt; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
<footer>{{ $document->title }}{{ data_get($document->metadata_json, 'source') === 'preset-demo' ? ' · Contoh - perlu ditinjau' : '' }} · Halaman <span class="page-number"></span></footer>

<header>
    <table class="letterhead"><tr>
        <td class="logo">@if($logoDataUris['left'])<img src="{{ $logoDataUris['left'] }}" alt="Logo kiri">@endif</td>
        <td class="school-title">
            @if(filled($document->school?->foundation_name))<p><strong>{{ strtoupper($document->school->foundation_name) }}</strong></p>@endif
            <h1>{{ $document->school?->name }}</h1>
            <p class="muted">{{ implode(' · ', array_filter([$document->school?->address, $document->school?->city, $document->school?->province])) }}</p>
            <p class="muted">{{ implode(' · ', array_filter([$document->school?->phone, $document->school?->email, $document->school?->website])) }}</p>
        </td>
        <td class="logo">@if($logoDataUris['right'])<img src="{{ $logoDataUris['right'] }}" alt="Logo kanan">@endif</td>
    </tr></table>
</header>

<h1 style="text-align:center">{{ $document->feature->code === 'rpp' ? 'Rencana Pelaksanaan Pembelajaran' : ($isQuestionPaper ? 'Lembar Soal' : $document->feature->name) }}</h1>
<table class="identity" style="margin-top:{{ $autoLandscape ? 10 : 18 }}px">
    <tr><td>Sekolah</td><td>: {{ data_get($content, 'identity.school') }}</td></tr>
    @if($document->vocationalProgram)<tr><td>Konsentrasi Keahlian</td><td>: {{ $document->vocationalProgram->name }}</td></tr>@endif
    <tr><td>Mata Pelajaran</td><td>: {{ data_get($content, 'identity.subject') }}</td></tr>
    <tr><td>Kelas / Semester</td><td>: {{ data_get($content, 'identity.class') }} / {{ data_get($content, 'identity.semester') }}</td></tr>
    <tr><td>Materi</td><td>: {{ $document->topic }}</td></tr>
    <tr><td>Tahun Pelajaran</td><td>: {{ data_get($content, 'identity.academic_year') }}</td></tr>
    @if(data_get($content, 'identity.time_allocation'))<tr><td>Alokasi Waktu</td><td>: {{ data_get($content, 'identity.time_allocation') }}</td></tr>@endif
    @if(data_get($content, 'identity.lesson_date'))<tr><td>Tanggal Belajar</td><td>: {{ \Illuminate\Support\Carbon::parse(data_get($content, 'identity.lesson_date'))->locale('id')->translatedFormat('d F Y') }}</td></tr>@endif
</table>
@if($isQuestionPaper || $isWorksheet)
    <table class="student-fields"><tr><td>Nama: ........................................................</td><td>{{ $isWorksheet ? 'Tanggal: ........................' : 'Nomor: ....................' }}</td></tr></table>
@endif
@if(! $isQuestionPaper && $curriculumSources->isNotEmpty())
    <div class="references">
        <strong>Rujukan kurikulum</strong>
        <p>CP/KD digunakan sebagai acuan; tujuan, kegiatan, dan asesmen perlu ditinjau guru sesuai konteks kelas.</p>
        <ol>@foreach($curriculumSources as $url)<li>{{ rawurldecode($url) }}</li>@endforeach</ol>
    </div>
@endif

@foreach($renderSections as $section)
    @continue($isQuestionPaper && $teacherSections->contains(fn ($teacherSection) => $teacherSection['key'] === ($section['key'] ?? null)))
    <h2 class="{{ $isWorksheet && ($section['key'] ?? null) === 'hasil' ? 'worksheet-results' : '' }}">{{ $section['title'] }}</h2>

    @if(($section['type'] ?? 'text') === 'text')
        @foreach(preg_split('/\r\n|\r|\n/', (string) ($section['content'] ?? '')) as $paragraph)
            @if(filled($paragraph))<p>{{ $paragraph }}</p>@endif
        @endforeach
    @elseif($section['type'] === 'list')
        <ol>
            @foreach(\App\Services\Documents\DocumentContentFormatter::list($section['content'] ?? []) as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ol>
    @elseif($section['type'] === 'activity_plan')
        @foreach($section['content'] ?? [] as $group)
            <div class="activity-group">
                @if(filled($group['title'] ?? null))<p class="activity-title"><strong>{{ $group['title'] }}</strong></p>@endif
                <ol>@foreach($group['steps'] ?? [] as $step)<li>{{ $step }}</li>@endforeach</ol>
            </div>
        @endforeach
    @elseif($section['type'] === 'table')
        <table class="border {{ $isWorksheet && ($section['key'] ?? null) === 'hasil' ? 'worksheet-answer' : '' }} {{ count($section['headers'] ?? []) >= 6 ? 'wide-table' : '' }}">
            @if(!empty($section['headers']))<thead><tr>@foreach($section['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>@endif
            <tbody>
                @foreach(($section['content'] ?? []) as $row)
                    <tr>@foreach((array) $row as $cell)<td>{{ \App\Services\Documents\DocumentContentFormatter::text($cell) }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @elseif($section['type'] === 'questions')
        @foreach(($section['content'] ?? []) as $question)
            <div class="question">
                <p><strong>{{ $question['number'] ?? $loop->iteration }}. {{ $question['question'] ?? '' }}</strong></p>
                @if(!empty($question['image']))@include('documents.partials.question-image', ['image' => $question['image'], 'pdf' => true])@endif
                @if(!empty($question['options']))
                    <div class="options">@foreach($question['options'] as $option)<p><strong>{{ $option['label'] ?? '' }}.</strong> {{ $option['content'] ?? '' }}</p>@endforeach</div>
                @endif
            </div>
        @endforeach
    @endif
@endforeach

@unless($isQuestionPaper || $isWorksheet)
    <table class="signatures"><tr>
        <td><p>Mengetahui,<br>Kepala Sekolah</p><div class="space"></div><p><strong><u>{{ $document->school?->principal_name ?: '..............................' }}</u></strong></p><p>NIP. {{ $document->school?->principal_identifier ?: '..............................' }}</p></td>
        <td><p>{{ $document->school?->city }}, {{ $document->created_at->locale('id')->translatedFormat('d F Y') }}<br>Guru Mata Pelajaran</p><div class="space"></div><p><strong><u>{{ $document->user->name }}</u></strong></p><p>NIP. ..............................</p></td>
    </tr></table>
@endunless

@if($isQuestionPaper && (($includeAnswer && $questionItems->isNotEmpty()) || $teacherSections->isNotEmpty() || $curriculumSources->isNotEmpty()))
    <div class="teacher-appendix">
        <h2>Lampiran untuk Guru</h2>
        @if($includeAnswer && $questionItems->isNotEmpty())
            <h2>Kunci Jawaban{{ $includeExplanation ? ' dan Pembahasan' : '' }}</h2>
            <table class="border answer-table">
                <thead><tr><th>No.</th><th>Jawaban</th>@if($includeExplanation)<th>Pembahasan</th>@endif</tr></thead>
                <tbody>@foreach($questionItems as $question)
                    <tr><td>{{ $question['number'] ?? $loop->iteration }}</td><td>{{ $question['answer'] ?? '' }}</td>@if($includeExplanation)<td>{{ $question['explanation'] ?? '' }}</td>@endif</tr>
                @endforeach</tbody>
            </table>
        @endif
        @foreach($teacherSections as $section)
            <h2>{{ $section['title'] }}</h2>
            <table class="border wide-table">
                <thead><tr>@foreach($section['headers'] ?? [] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
                <tbody>@foreach($section['content'] ?? [] as $row)<tr>@foreach((array) $row as $cell)<td>{{ \App\Services\Documents\DocumentContentFormatter::text($cell) }}</td>@endforeach</tr>@endforeach</tbody>
            </table>
        @endforeach
        @if($curriculumSources->isNotEmpty())
            <div class="references">
                <strong>Rujukan kurikulum</strong>
                <ol>@foreach($curriculumSources as $url)<li>{{ rawurldecode($url) }}</li>@endforeach</ol>
            </div>
        @endif
    </div>
@endif
</body>
</html>
