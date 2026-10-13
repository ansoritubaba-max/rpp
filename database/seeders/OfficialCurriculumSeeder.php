<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\EducationLevel;
use App\Models\Subject;
use App\Models\VocationalProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports phase-level CP from BSKAP 046/H/KR/2025. The PDF was extracted and
 * checked at development time; production seeding is deterministic/offline.
 *
 * Religious CP is intentionally omitted because decision 020/2026 amended it.
 * TP is not official prescribed content, so this seeder does not invent TP.
 */
class OfficialCurriculumSeeder extends Seeder
{
    private const GENERAL_CODES = [
        'PENDIDIKAN PANCASILA' => 'PPKN',
        'BAHASA INDONESIA' => 'BIND',
        'MATEMATIKA' => 'MAT',
        'BAHASA INGGRIS' => 'BIG',
        'ILMU PENGETAHUAN ALAM DAN SOSIAL (IPAS)' => 'IPAS',
        'ILMU PENGETAHUAN ALAM (IPA)' => 'IPA',
        'ILMU PENGETAHUAN SOSIAL' => 'IPS',
        'INFORMATIKA' => 'INF',
        'SEJARAH' => 'SEJ',
        'FISIKA' => 'FIS',
        'KIMIA' => 'KIM',
        'BIOLOGI' => 'BIO',
        'GEOGRAFI' => 'GEO',
        'EKONOMI' => 'EKO',
        'SOSIOLOGI' => 'SOS',
        'ANTROPOLOGI' => 'ANT',
        'KODING DAN KECERDASAN ARTIFISIAL' => 'KKA',
        'PENDIDIKAN JASMANI, OLAHRAGA, DAN KESEHATAN' => 'PJOK',
    ];

    private const SMK_SHARED = [
        'PENDIDIKAN PANCASILA',
        'BAHASA INDONESIA',
        'MATEMATIKA',
        'BAHASA INGGRIS',
        'PENDIDIKAN JASMANI, OLAHRAGA, DAN KESEHATAN',
        'SENI MUSIK',
        'SENI RUPA',
        'SENI TARI',
        'SENI TEATER',
    ];

    private const EXISTING_VOCATIONAL_CODES = [
        'TEKNIK KOMPUTER DAN JARINGAN' => 'TKJ',
        'TEKNIK KENDARAAN RINGAN' => 'TKR',
        'TEKNIK SEPEDA MOTOR' => 'TBSM',
        'DESAIN KOMUNIKASI VISUAL' => 'DKV',
        'KULINER' => 'TBG',
    ];

    public function run(): void
    {
        $path = database_path('data/official_cp_2025.json');
        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (($data['decision'] ?? null) !== '046/H/KR/2025' || count($data['entries'] ?? []) < 220) {
            throw new RuntimeException('Dataset CP resmi tidak lengkap atau versinya tidak sesuai.');
        }

        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $levels = EducationLevel::query()->with('grades')->get()->keyBy('code');
        $smk = $levels->get('SMK');
        if (! $smk) {
            throw new RuntimeException('Jenjang SMK harus tersedia sebelum memuat CP resmi.');
        }

        $basic = collect($data['entries'])->filter(fn (array $entry) => str_starts_with($entry['section'], 'V.') && $entry['page'] >= 348)->keyBy('section');
        foreach ($data['entries'] as $entry) {
            if ($entry['page'] < 348) {
                $this->seedGeneral($curriculum, $levels, $entry, $data['source']);
                continue;
            }

            if (str_starts_with($entry['section'], 'VI.')) {
                $this->seedVocational($curriculum, $smk, $entry, $basic->get($entry['basic_section']), $data['source']);
                continue;
            }

            if (! str_starts_with($entry['section'], 'V.')) {
                $this->seedSmkCommon($curriculum, $smk, $entry, $data['source']);
            }
        }

        // Provide a subject choice for religion without claiming that the
        // superseded 2025 CP is current. Current CP must be entered from 020/2026.
        foreach ($levels as $level) {
            $level->subjects()->firstOrCreate(
                ['code' => 'PAI'],
                ['name' => 'Pendidikan Agama Islam dan Budi Pekerti', 'is_religious' => true, 'is_active' => true],
            );
        }
    }

    private function seedGeneral(Curriculum $curriculum, $levels, array $entry, string $source): void
    {
        $title = $entry['title'];
        $code = self::GENERAL_CODES[$title] ?? 'CP'.preg_replace('/[^A-Z0-9]/', '', $entry['section']);
        foreach ($entry['phases'] as $phase => $content) {
            $levelCodes = match ($phase) {
                'A', 'B', 'C' => ['SD', 'MI'],
                'D' => ['SMP', 'MTS'],
                'E', 'F' => ['SMA', 'MA'],
                default => [],
            };
            if (in_array($title, self::SMK_SHARED, true) && in_array($phase, ['E', 'F'], true)) {
                $levelCodes[] = 'SMK';
            }
            foreach ($levelCodes as $levelCode) {
                $level = $levels->get($levelCode);
                if (! $level) {
                    continue;
                }
                $subject = $level->subjects()->firstOrCreate(
                    ['code' => $code],
                    ['name' => $this->displayName($title), 'is_religious' => false, 'is_active' => true],
                );
                $this->seedPhase($curriculum, $level, $subject, $phase, $content, $source, $entry['page']);
            }
        }
    }

    private function seedSmkCommon(Curriculum $curriculum, EducationLevel $level, array $entry, string $source): void
    {
        $code = match ($entry['section']) {
            'II' => 'SEJ',
            'III' => 'PIPAS',
            'IV' => 'KKA',
            'VII' => 'KIK',
            'IX' => 'PKL',
            default => null,
        };
        if (! $code) {
            return;
        }
        $name = $entry['section'] === 'II' ? 'Sejarah' : $this->displayName($entry['title']);
        $subject = $level->subjects()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'is_religious' => false, 'is_active' => true],
        );
        foreach ($entry['phases'] as $phase => $content) {
            $this->seedPhase($curriculum, $level, $subject, $phase, $content, $source, $entry['page']);
        }
    }

    private function seedVocational(Curriculum $curriculum, EducationLevel $level, array $entry, ?array $basic, string $source): void
    {
        $number = (int) substr($entry['section'], 3);
        $code = self::EXISTING_VOCATIONAL_CODES[$entry['title']] ?? sprintf('KJ%03d', $number);
        $program = $level->vocationalPrograms()->firstOrCreate(
            ['code' => $code],
            ['name' => $this->displayName($entry['title']), 'is_active' => true, 'sort_order' => $number + 10],
        );
        $subject = $level->subjects()->firstOrCreate(
            ['code' => $code],
            ['name' => $this->displayName($entry['title']), 'vocational_program_id' => $program->id, 'is_religious' => false, 'is_active' => true],
        );
        foreach ($entry['phases'] as $phase => $content) {
            $this->seedPhase($curriculum, $level, $subject, $phase, $content, $source, $entry['page'], $program);
        }

        if ($basic) {
            $basicNumber = (int) substr($basic['section'], 2);
            $basicSubject = $level->subjects()->firstOrCreate(
                ['code' => sprintf('D%02d_%s', $basicNumber, $code)],
                ['name' => $this->displayName($basic['title']), 'vocational_program_id' => $program->id, 'is_religious' => false, 'is_active' => true],
            );
            foreach ($basic['phases'] as $phase => $content) {
                $this->seedPhase($curriculum, $level, $basicSubject, $phase, $content, $source, $basic['page'], $program);
            }
        }
    }

    private function seedPhase(
        Curriculum $curriculum,
        EducationLevel $level,
        Subject $subject,
        string $phase,
        string $content,
        string $source,
        int $page,
        ?VocationalProgram $program = null,
    ): void {
        foreach ($level->grades->where('phase', $phase) as $grade) {
            $context = CurriculumSubject::query()->firstOrCreate(
                [
                    'curriculum_id' => $curriculum->id,
                    'education_level_id' => $level->id,
                    'vocational_program_id' => $program?->id,
                    'grade_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'semester' => null,
                ],
                ['is_active' => true],
            );
            $context->learningOutcomes()->firstOrCreate(
                ['phase' => $phase, 'element' => 'CP resmi 046/H/KR/2025'],
                ['content' => $content, 'source_url' => $source.'#page='.$page, 'source_kind' => 'official'],
            );
        }
    }

    private function displayName(string $title): string
    {
        return preg_replace_callback(
            '/\b(Dan|Di|Ke|Dari|Untuk)\b/u',
            fn (array $match) => mb_strtolower($match[0]),
            Str::title(Str::lower($title)),
        );
    }
}
