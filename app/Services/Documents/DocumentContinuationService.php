<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

class DocumentContinuationService
{
    private const SOURCES = [
        'promes' => ['prota'],
        'atp' => ['promes'],
        'rpp' => ['atp', 'promes'],
        'modul_ajar' => ['atp', 'promes'],
        'lkpd' => ['rpp', 'modul_ajar'],
        'soal' => ['lkpd'],
        'kisi_kisi' => ['soal'],
    ];

    public function available(User $user, string $targetCode, ?string $selectedUuid = null): Collection
    {
        $documents = Document::query()
            ->with('feature')
            ->where('user_id', $user->id)
            ->whereIn('status', ['generated', 'edited'])
            ->whereHas('feature', fn ($query) => $query->whereIn('code', self::SOURCES[$targetCode] ?? []))
            ->latest()
            ->limit(100)
            ->get();

        $selected = $this->find($user, $targetCode, $selectedUuid);
        if ($selected && ! $documents->contains('id', $selected->id)) {
            $documents->prepend($selected);
        }

        return $documents;
    }

    public function find(User $user, string $targetCode, ?string $uuid): ?Document
    {
        if (blank($uuid)) {
            return null;
        }

        return Document::query()
            ->with('feature')
            ->where('user_id', $user->id)
            ->where('uuid', $uuid)
            ->whereIn('status', ['generated', 'edited'])
            ->whereHas('feature', fn ($query) => $query->whereIn('code', self::SOURCES[$targetCode] ?? []))
            ->first();
    }

    public function nextCodes(Document $document): array
    {
        if (! in_array($document->status, ['generated', 'edited'], true)) {
            return [];
        }

        $code = $document->feature->code;
        $next = match ($code) {
            'prota' => ['promes'],
            'promes' => ['atp', 'rpp', 'modul_ajar'],
            'atp' => ['rpp', 'modul_ajar'],
            'rpp', 'modul_ajar' => ['lkpd'],
            'lkpd' => ['soal'],
            'soal' => ['kisi_kisi'],
            default => [],
        };

        return $document->curriculum?->code === 'KM' ? $next : array_values(array_diff($next, ['atp']));
    }

    public function prefill(Document $source, string $targetCode): array
    {
        $fields = [
            'schoolId' => $source->school_id,
            'academicYear' => $source->academic_year,
            'curriculumId' => $source->curriculum_id,
            'educationLevelId' => $source->education_level_id,
            'vocationalProgramId' => $source->vocational_program_id,
            'gradeId' => $source->grade_id,
            'subjectId' => $source->subject_id,
            'semester' => $source->semester ?: 1,
            'topic' => $source->topic ?? '',
            'subtopic' => $source->subtopic ?? '',
        ];

        if ($targetCode === 'promes') {
            $fields['topicList'] = implode("\n", $this->context($source, 'promes', $fields['semester'])['topics']);
            $fields['effectiveWeeks'] = min(30, max(1, (int) data_get($source->metadata_json, 'settings.effective_weeks', 18)));
        }

        if ($targetCode === 'atp') {
            $fields['topicList'] = implode("\n", $this->tableColumn($source, 'program_semester', 1));
            $fields['topic'] = $this->tableColumn($source, 'program_semester', 1)[0] ?? $fields['topic'];
        }

        if (in_array($targetCode, ['lkpd', 'soal'], true)) {
            $fields['readingAbility'] = data_get($source->metadata_json, 'settings.reading_ability')
                ?? data_get($source->metadata_json, 'reading_ability')
                ?? 'normal';
        }

        return $fields;
    }

    public function assertCompatible(Document $source, array $data, string $targetCode): void
    {
        foreach (['school_id', 'curriculum_id', 'education_level_id', 'grade_id', 'subject_id'] as $field) {
            if ((int) $source->{$field} !== (int) ($data[$field] ?? 0)) {
                throw new DomainException('Sekolah, kurikulum, kelas, dan mata pelajaran harus sama dengan dokumen sumber. Pilih sumber lain atau kosongkan pilihan sumber.');
            }
        }

        if ((int) $source->vocational_program_id !== (int) ($data['vocational_program_id'] ?? 0)) {
            throw new DomainException('Konsentrasi keahlian harus sama dengan dokumen sumber.');
        }

        if ($source->academic_year !== ($data['academic_year'] ?? null)) {
            throw new DomainException('Tahun pelajaran harus sama dengan dokumen sumber.');
        }

        if ($source->feature->code !== 'prota' && (int) $source->semester !== (int) ($data['semester'] ?? 0)) {
            throw new DomainException('Semester harus sama dengan dokumen sumber.');
        }

        if (in_array($targetCode, ['lkpd', 'soal', 'kisi_kisi'], true)
            && mb_strtolower(trim((string) $source->topic)) !== mb_strtolower(trim((string) ($data['topic'] ?? '')))) {
            throw new DomainException('Materi harus sama dengan dokumen sumber agar isi perangkat tetap konsisten.');
        }

        if ($source->feature->code === 'atp' && in_array($targetCode, ['rpp', 'modul_ajar'], true)) {
            $topics = $this->tableColumn($source, 'alur_tujuan', 2);
            if ($topics !== [] && ! in_array(mb_strtolower(trim((string) ($data['topic'] ?? ''))), array_map('mb_strtolower', $topics), true)) {
                throw new DomainException('Materi RPP/Modul harus termasuk dalam alur tujuan pada ATP sumber.');
            }
        }
    }

    public function context(Document $source, string $targetCode, int $semester, ?string $topic = null): array
    {
        $context = ['source_title' => $source->title, 'source_type' => $source->feature->code];

        if ($targetCode === 'promes') {
            $context['topics'] = $this->tableColumn($source, 'program_tahunan', 1, $semester === 1 ? 'Ganjil' : 'Genap');
        } elseif ($targetCode === 'atp') {
            $context['topics'] = $this->tableColumn($source, 'program_semester', 1);
        } elseif (in_array($targetCode, ['rpp', 'modul_ajar', 'lkpd'], true)) {
            $context['objectives'] = $this->objectives($source, $topic);
        } elseif ($targetCode === 'soal') {
            $context['activities'] = $this->sectionLines($source, 'aktivitas');
        } elseif ($targetCode === 'kisi_kisi') {
            $context['questions'] = $source->questions()
                ->orderBy('id')
                ->limit(50)
                ->get(['id', 'topic', 'question_type', 'question_text', 'cognitive_level'])
                ->map(fn ($question) => [
                    'topic' => $this->clean($question->topic),
                    'type' => $this->clean($question->question_type),
                    'question' => $this->clean($question->question_text),
                    'level' => $this->clean($question->cognitive_level),
                ])->all();
        }

        return $context;
    }

    private function objectives(Document $source, ?string $topic): array
    {
        return match ($source->feature->code) {
            'atp' => $this->atpObjectives($source, $topic),
            'rpp' => $this->cleanLines(data_get($source->content_json, 'objectives', [])),
            'modul_ajar' => $this->sectionLines($source, 'tujuan'),
            default => [],
        };
    }

    private function atpObjectives(Document $source, ?string $topic): array
    {
        $section = collect(data_get($source->content_json, 'sections', []))->firstWhere('key', 'alur_tujuan');
        $rows = collect($section['content'] ?? []);
        if (filled($topic)) {
            $rows = $rows->filter(fn ($row) => is_array($row)
                && mb_strtolower(trim((string) ($row[2] ?? ''))) === mb_strtolower(trim($topic)));
        }

        return $this->cleanLines($rows->map(fn ($row) => is_array($row) ? ($row[1] ?? null) : null)->all());
    }

    private function sectionLines(Document $source, string $key): array
    {
        $section = collect(data_get($source->content_json, 'sections', []))->firstWhere('key', $key);

        return $this->cleanLines($section['content'] ?? []);
    }

    private function tableColumn(Document $source, string $key, int $column, ?string $semester = null): array
    {
        $section = collect(data_get($source->content_json, 'sections', []))->firstWhere('key', $key);
        $rows = collect($section['content'] ?? []);
        if ($semester !== null) {
            $filtered = $rows->filter(fn ($row) => is_array($row) && ($row[0] ?? null) === $semester);
            if ($filtered->isNotEmpty()) {
                $rows = $filtered;
            }
        }

        return $this->cleanLines($rows->map(fn ($row) => is_array($row) ? ($row[$column] ?? null) : null)->all());
    }

    private function cleanLines(mixed $lines): array
    {
        return collect(is_array($lines) ? $lines : [$lines])
            ->filter(fn ($line) => is_scalar($line))
            ->map(fn ($line) => $this->clean($line))
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();
    }

    private function clean(mixed $value): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, 500);
    }
}
