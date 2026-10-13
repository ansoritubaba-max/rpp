<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Feature;
use App\Models\User;
use App\Services\AI\AiManager;
use Illuminate\Support\Str;

class GenericDocumentService
{
    public function __construct(private readonly AiManager $aiManager, private readonly QuestionAssetLibrary $assets) {}

    public function create(User $user, Feature $feature, array $data): Document
    {
        $deterministicContinuation = filled($data['source_document_id'] ?? null)
            && in_array($feature->code, ['promes', 'atp', 'kisi_kisi'], true);
        $visualQuestions = $feature->code === 'soal' && ($data['settings']['illustrated_questions'] ?? false);
        $lowReaderWorksheet = $feature->code === 'lkpd' && ($data['grade_numeric'] ?? 0) <= 3 && ($data['settings']['reading_ability'] ?? null) === 'belum_lancar';
        $ai = ($deterministicContinuation || $visualQuestions || $lowReaderWorksheet) ? null : $this->aiManager->generate($feature->code, $feature->code.'_sections', [
            'curriculum' => $data['curriculum_name'],
            'level' => $data['level_name'],
            'vocational_program' => $data['vocational_program_name'] ?? null,
            'grade' => $data['grade_name'],
            'subject' => $data['subject_name'],
            'topic' => $data['topic'],
            'subtopic' => $data['subtopic'] ?? null,
            'semester' => $data['semester'],
            'settings' => $data['settings'],
            'teacher_notes' => $data['teacher_notes'] ?? null,
            'curriculum_reference' => $data['curriculum_context'] ?? [],
            'source_reference' => $data['source_context'] ?? [],
        ], ['sections']);

        $sections = $ai['sections'] ?? $this->presetSections($feature->code, $data);

        if (in_array($feature->code, ['modul_ajar', 'lkpd'], true) && ! empty($data['source_context']['objectives'])) {
            $foundObjectives = false;
            foreach ($sections as &$section) {
                if (($section['key'] ?? null) === 'tujuan') {
                    $section['type'] = 'list';
                    $section['content'] = $data['source_context']['objectives'];
                    $foundObjectives = true;
                    break;
                }
            }
            unset($section);
            if (! $foundObjectives) {
                array_unshift($sections, $this->list('tujuan', 'Tujuan Pembelajaran', $data['source_context']['objectives']));
            }
        }

        $content = [
            'identity' => [
                'school' => $data['school_name'],
                'teacher' => $data['teacher_name'],
                'subject' => $data['subject_name'],
                'class' => $data['grade_name'],
                'semester' => $data['semester'],
                'academic_year' => $data['academic_year'],
                'curriculum' => $data['curriculum_name'],
                'vocational_program' => $data['vocational_program_name'] ?? null,
                'topic' => $data['topic'],
            ],
            'sections' => $sections,
        ];

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'school_id' => $data['school_id'],
            'feature_id' => $feature->id,
            'source_document_id' => $data['source_document_id'] ?? null,
            'curriculum_id' => $data['curriculum_id'],
            'education_level_id' => $data['education_level_id'],
            'vocational_program_id' => $data['vocational_program_id'] ?? null,
            'grade_id' => $data['grade_id'],
            'subject_id' => $data['subject_id'],
            'academic_year' => $data['academic_year'],
            'semester' => $data['semester'],
            'title' => $this->title($feature, $data),
            'topic' => $data['topic'],
            'subtopic' => $data['subtopic'] ?? null,
            'content_json' => $content,
            'metadata_json' => [
                'settings' => $data['settings'],
                'source' => $ai ? 'hybrid-ai' : ($deterministicContinuation ? 'linked-preset' : ($visualQuestions ? 'visual-preset' : 'preset-demo')),
                'curriculum_database' => ($data['curriculum_context']['outcomes'] ?? []) !== [] || ($data['curriculum_context']['competencies'] ?? []) !== [],
                'curriculum_sources' => $data['curriculum_context']['sources'] ?? [],
            ],
            'status' => 'generated',
        ]);

        $document->versions()->create([
            'version_number' => 1,
            'content_json' => $content,
            'created_by' => $user->id,
        ]);

        if ($feature->code === 'soal') {
            $this->storeQuestions($user, $document, $data, $sections);
        }

        return $document;
    }

    private function title(Feature $feature, array $data): string
    {
        return "{$feature->name} {$data['subject_name']} — {$data['topic']}";
    }

    private function presetSections(string $featureCode, array $data): array
    {
        return match ($featureCode) {
            'modul_ajar' => $this->moduleSections($data),
            'silabus' => $this->syllabusSections($data),
            'lkpd' => $this->worksheetSections($data),
            'soal' => $this->questionSections($data),
            'kisi_kisi' => $this->blueprintSections($data),
            'prota' => $this->annualProgramSections($data),
            'promes' => $this->semesterProgramSections($data),
            'atp' => $this->learningPathSections($data),
            default => [],
        };
    }

    private function moduleSections(array $data): array
    {
        $topic = $data['topic'];
        $settings = $data['settings'];
        $objectives = $data['source_context']['objectives'] ?? [];
        if ($objectives === []) {
            $objectives = $data['curriculum_context']['objectives'] ?? [];
        }
        $objectives = $objectives ?: ["Peserta didik mampu menjelaskan konsep utama {$topic} dengan bahasa sendiri.", "Peserta didik mampu menerapkan konsep {$topic} melalui tugas kontekstual.", 'Peserta didik menunjukkan sikap aktif, teliti, dan bertanggung jawab.'];

        return [
            $this->text('informasi_umum', 'Informasi Umum', "Modul ini membahas {$topic} untuk {$data['grade_name']} dengan model {$settings['learning_model']}. Kondisi awal peserta didik: {$settings['student_condition']}."),
            $this->list('kompetensi_awal', 'Kompetensi Awal', ["Mengidentifikasi pengetahuan awal yang berkaitan dengan {$topic}.", 'Mengomunikasikan pengalaman atau contoh yang dijumpai dalam kehidupan sehari-hari.']),
            $this->list('tujuan', 'Tujuan Pembelajaran', $objectives),
            $this->text('pemahaman_bermakna', 'Pemahaman Bermakna', "Pemahaman tentang {$topic} membantu peserta didik mengambil keputusan dan memecahkan persoalan yang dekat dengan kehidupan mereka."),
            $this->list('pertanyaan_pemantik', 'Pertanyaan Pemantik', ["Apa yang sudah kamu ketahui tentang {$topic}?", "Di mana kamu menemukan penerapan {$topic} dalam kehidupan sehari-hari?", 'Bagaimana kamu menjelaskan konsep ini kepada temanmu?']),
            $this->list('kegiatan', 'Kegiatan Pembelajaran', ['Pendahuluan: salam, apersepsi, penyampaian tujuan, dan kesepakatan belajar.', "Inti: eksplorasi masalah {$topic}, kerja kelompok, presentasi, dan penguatan konsep.", 'Penutup: menyusun simpulan, refleksi, umpan balik, dan tindak lanjut.']),
            $this->table('asesmen', 'Asesmen', ['Tahap', 'Teknik', 'Instrumen'], [['Diagnostik', 'Tanya jawab', 'Pertanyaan pemantik'], ['Formatif', 'Observasi dan produk', 'Rubrik aktivitas'], ['Sumatif', 'Tes tertulis', 'Soal pemahaman dan penerapan']]),
            $this->list('tindak_lanjut', 'Remedial, Pengayaan, dan Refleksi', ['Remedial melalui penjelasan ulang dan latihan bertahap.', 'Pengayaan melalui tantangan penerapan pada konteks baru.', 'Guru dan peserta didik mencatat hal yang sudah dipahami serta rencana perbaikan.']),
        ];
    }

    private function syllabusSections(array $data): array
    {
        $topics = $this->topics($data);
        $curriculumReference = collect($data['curriculum_context']['outcomes'] ?? [])->merge($data['curriculum_context']['competencies'] ?? [])->first();
        $rows = collect($topics)->values()->map(fn ($topic, $index) => [
            $topic,
            $curriculumReference ?: "Memahami konsep dan penerapan {$topic}",
            "Menjelaskan dan menggunakan konsep {$topic}",
            'Eksplorasi, diskusi, praktik, dan refleksi',
            'Observasi, produk, dan tes tertulis',
            ($index + 1).' minggu',
            'Buku teks dan lingkungan sekitar',
        ])->all();

        return [
            $this->text('rasional', 'Rasional', "Silabus {$data['subject_name']} disusun agar pembelajaran {$data['grade_name']} berlangsung terarah, kontekstual, dan terukur."),
            $this->table('silabus', 'Pemetaan Pembelajaran', ['Materi', 'Capaian/KD', 'Tujuan/Indikator', 'Kegiatan', 'Asesmen', 'Alokasi', 'Sumber'], $rows),
        ];
    }

    private function worksheetSections(array $data): array
    {
        $count = (int) $data['settings']['activity_count'];
        $earlyReader = ($data['grade_numeric'] ?? 0) <= 3 && ($data['settings']['reading_ability'] ?? null) === 'belum_lancar';
        $topic = $data['topic'];
        $activitySteps = $earlyReader
            ? [
                "Amati gambar atau benda yang menunjukkan {$topic} bersama guru.",
                "Tunjuk bagian yang berkaitan dengan {$topic} dan sebutkan namanya.",
                'Coba satu contoh bersama teman dengan bantuan guru.',
                'Pilih jawaban dari dua contoh yang diperlihatkan guru.',
                'Susun atau gambar satu contoh baru di lembar hasil.',
                'Ceritakan hasil pekerjaanmu dengan kalimat pendek.',
                'Bandingkan pekerjaanmu dengan contoh dari teman.',
                'Perbaiki bagian yang belum tepat setelah mendapat bantuan.',
                'Tunjukkan satu hal yang sudah kamu pahami.',
                'Sampaikan satu hal yang ingin kamu tanyakan kepada guru.',
            ]
            : [
                "Amati dua contoh {$topic} yang disiapkan guru dan catat cirinya.",
                'Tuliskan persamaan dan perbedaan dari kedua contoh tersebut.',
                "Diskusikan hubungan temuanmu dengan konsep {$topic} bersama kelompok.",
                'Buat satu contoh baru berdasarkan hasil diskusi.',
                'Jelaskan langkah yang kamu gunakan untuk membuat contoh tersebut.',
                'Tukar hasil pekerjaan dengan teman dan berikan satu pertanyaan.',
                'Periksa kembali jawabanmu menggunakan umpan balik teman.',
                'Terapkan konsep pada situasi lain di lingkungan sekitar.',
                'Tuliskan simpulan dengan bahasamu sendiri dan sertakan alasan.',
                'Refleksikan bagian yang sudah dipahami dan yang perlu dilatih lagi.',
            ];
        $activities = collect(array_slice($activitySteps, 0, $count))
            ->values()->map(fn ($step, $index) => 'Aktivitas '.($index + 1).': '.$step)->all();
        $objectives = $data['source_context']['objectives'] ?? [];
        if ($objectives === []) {
            $objectives = ["Menemukan konsep penting pada materi {$data['topic']}.", 'Menyajikan hasil pengamatan secara runtut.', 'Merefleksikan proses dan hasil belajar.'];
        }

        return [
            $this->text('petunjuk', 'Petunjuk Belajar', $earlyReader ? 'Dengar arahan guru. Lihat gambar atau contoh. Kerjakan satu per satu.' : 'Baca setiap instruksi, siapkan alat yang diperlukan, bekerja secara tertib, dan tanyakan kepada guru jika ada bagian yang belum dipahami.'),
            $this->list('tujuan', 'Tujuan Kegiatan', $objectives),
            $this->list('aktivitas', 'Langkah Aktivitas', $activities),
            $this->table('hasil', 'Lembar Hasil', ['Bagian', 'Catatan Peserta Didik'], [['Hasil pengamatan', ''], ['Jawaban/produk', ''], ['Simpulan', ''], ['Refleksi', '']]),
        ];
    }

    private function questionSections(array $data): array
    {
        $count = (int) $data['settings']['question_count'];
        $type = $data['settings']['question_type'];
        $visual = (bool) ($data['settings']['illustrated_questions'] ?? false);
        $asset = $this->assets->supports($data['settings']['image_asset'] ?? null) ? $data['settings']['image_asset'] : 'apple';
        $baseCount = max(1, min(8, (int) ($data['settings']['image_count'] ?? 3)));
        $earlyReader = ($data['grade_numeric'] ?? 0) <= 3 && ($data['settings']['reading_ability'] ?? null) === 'belum_lancar';
        $items = collect(range(1, $count))->map(function ($number) use ($data, $type, $visual, $asset, $baseCount, $earlyReader) {
            $multipleChoice = $type === 'pilihan_ganda' || ($type === 'campuran' && $number % 2 === 1);
            if ($visual) {
                $quantity = min(10, $baseCount + (($number - 1) % 3));
                $options = $multipleChoice ? collect(range(1, $earlyReader ? 3 : 4))->map(fn ($index) => [
                    'label' => chr(64 + $index),
                    'content' => (string) ($quantity + ($index === 1 ? 1 : ($index === 2 ? 0 : $index - 1))),
                    'is_correct' => $index === 2,
                ])->all() : [];

                return [
                    'number' => $number,
                    'type' => $multipleChoice ? 'pilihan_ganda' : 'uraian',
                    'difficulty' => $data['settings']['difficulty'],
                    'cognitive_level' => 'C1',
                    'question' => 'Hitung jumlah '.$this->assets->noun($asset).' pada gambar.',
                    'image' => ['asset' => $asset, 'count' => $quantity],
                    'answer' => $multipleChoice ? 'B' : (string) $quantity,
                    'explanation' => 'Ada '.$quantity.' '.$this->assets->noun($asset).' pada gambar.',
                    'options' => $options,
                ];
            }
            $question = [
                'number' => $number,
                'type' => $multipleChoice ? 'pilihan_ganda' : 'uraian',
                'difficulty' => $data['settings']['difficulty'],
                'cognitive_level' => $data['settings']['hots'] ? 'C4' : 'C2',
                'question' => $multipleChoice
                    ? "Pernyataan yang paling tepat tentang {$data['topic']} adalah ...."
                    : "Jelaskan konsep {$data['topic']} dan berikan satu contoh penerapannya.",
                'answer' => $multipleChoice ? 'A' : "Jawaban memuat penjelasan yang tepat tentang {$data['topic']} beserta contoh yang relevan.",
                'explanation' => "Jawaban dinilai dari ketepatan konsep {$data['topic']}, kelengkapan alasan, dan relevansi contoh.",
                'options' => [],
            ];

            if ($multipleChoice) {
                $question['options'] = [
                    ['label' => 'A', 'content' => "Pernyataan yang sesuai dengan konsep utama {$data['topic']}.", 'is_correct' => true],
                    ['label' => 'B', 'content' => "Pernyataan yang tidak berkaitan langsung dengan {$data['topic']}.", 'is_correct' => false],
                    ['label' => 'C', 'content' => 'Pernyataan yang membalik hubungan antar konsep.', 'is_correct' => false],
                    ['label' => 'D', 'content' => 'Pernyataan yang hanya memuat contoh tanpa konsep.', 'is_correct' => false],
                ];
            }

            return $question;
        })->all();

        $sections = [$this->questions('daftar_soal', 'Daftar Soal', $items)];
        if ($data['settings']['include_blueprint']) {
            $sections[] = $this->table('kisi_kisi_ringkas', 'Kisi-Kisi Ringkas', ['No.', 'Materi', 'Indikator', 'Level', 'Bentuk'], collect($items)->map(fn ($item) => [$item['number'], $data['topic'], 'Mengukur pemahaman dan penerapan konsep', $item['cognitive_level'], str($item['type'])->replace('_', ' ')->title()])->all());
        }

        return $sections;
    }

    private function blueprintSections(array $data): array
    {
        if (($data['settings']['source_mode'] ?? 'materi') === 'dokumen' && ! empty($data['source_context']['questions'])) {
            $rows = collect($data['source_context']['questions'])->values()->map(fn ($question, $index) => [
                $index + 1,
                $question['topic'],
                $question['question'],
                $question['level'] ?: $data['settings']['cognitive_level'],
                str($question['type'])->replace('_', ' ')->title()->toString(),
                $index + 1,
            ])->all();

            return [$this->table('kisi_kisi', 'Kisi-Kisi Penilaian', ['No.', 'Materi', 'Indikator Soal', 'Level Kognitif', 'Bentuk', 'No. Soal'], $rows)];
        }

        $count = (int) $data['settings']['question_count'];
        $rows = collect(range(1, $count))->map(fn ($number) => [$number, $data['topic'], "Disajikan konteks, peserta didik dapat menjelaskan atau menerapkan konsep {$data['topic']}.", $data['settings']['cognitive_level'], $number % 3 === 0 ? 'Uraian' : 'Pilihan Ganda', $number])->all();

        return [$this->table('kisi_kisi', 'Kisi-Kisi Penilaian', ['No.', 'Materi', 'Indikator Soal', 'Level Kognitif', 'Bentuk', 'No. Soal'], $rows)];
    }

    private function annualProgramSections(array $data): array
    {
        $topics = $this->topics($data);
        $totalJp = (int) $data['settings']['total_jp'];
        $allocation = max(1, intdiv($totalJp, max(1, count($topics))));
        $rows = collect($topics)->values()->map(fn ($topic, $index) => [$index < (int) ceil(count($topics) / 2) ? 'Ganjil' : 'Genap', $topic, $allocation.' JP', 'Disesuaikan kalender pendidikan'])->all();

        return [
            $this->text('landasan', 'Landasan Penyusunan', "Program tahunan disusun berdasarkan {$data['curriculum_name']}, kalender pendidikan, dan alokasi {$totalJp} JP."),
            $this->table('program_tahunan', 'Program Tahunan', ['Semester', 'Materi/Unit', 'Alokasi Waktu', 'Keterangan'], $rows),
        ];
    }

    private function semesterProgramSections(array $data): array
    {
        $topics = $this->topics($data);
        $weeks = (int) $data['settings']['effective_weeks'];
        $perTopic = max(1, intdiv($weeks, max(1, count($topics))));
        $rows = collect($topics)->values()->map(fn ($topic, $index) => ['Minggu '.(($index * $perTopic) + 1).'–'.min($weeks, ($index + 1) * $perTopic), $topic, $perTopic.' minggu', 'Pembelajaran, asesmen, dan tindak lanjut'])->all();

        return [
            $this->text('ringkasan', 'Ringkasan Semester', "Program semester {$data['semester']} memiliki {$weeks} minggu efektif dan dapat disesuaikan dengan agenda satuan pendidikan."),
            $this->table('program_semester', 'Program Semester', ['Waktu', 'Materi/Unit', 'Alokasi', 'Kegiatan'], $rows),
        ];
    }

    private function learningPathSections(array $data): array
    {
        $count = (int) $data['settings']['objective_count'];
        $databaseOutcome = collect($data['curriculum_context']['outcomes'] ?? [])->first();
        $topics = $this->topics($data);
        $rows = collect(range(1, $count))->map(function ($number) use ($count, $topics) {
            $topic = $topics[($number - 1) % count($topics)];

            return [$number, 'Peserta didik mampu '.($number === 1 ? 'mengidentifikasi' : ($number === $count ? 'mengevaluasi' : 'menerapkan'))." konsep {$topic} secara bertahap.", $topic, '2–4 JP', $number === $count ? 'Sumatif' : 'Formatif'];
        })->all();

        return [
            $this->text('capaian', 'Capaian Pembelajaran', $databaseOutcome ?: $data['settings']['learning_outcome']),
            $this->text('rasional', 'Rasional Alur', "Alur disusun dari pemahaman dasar menuju penerapan dan refleksi pada materi {$data['topic']} untuk fase {$data['grade_phase']}."),
            $this->table('alur_tujuan', 'Alur Tujuan Pembelajaran', ['Urutan', 'Tujuan Pembelajaran', 'Materi', 'Alokasi', 'Asesmen'], $rows),
        ];
    }

    private function topics(array $data): array
    {
        $topics = preg_split('/\r\n|\r|\n/', (string) ($data['settings']['topic_list'] ?? ''));
        $topics = collect($topics)->map(fn ($topic) => trim($topic))->filter()->values()->all();

        return $topics ?: [$data['topic']];
    }

    private function storeQuestions(User $user, Document $document, array $data, array $sections): void
    {
        $items = collect($sections)->firstWhere('type', 'questions')['content'] ?? [];

        foreach ($items as $item) {
            $question = $user->questions()->create([
                'school_id' => $data['school_id'],
                'document_id' => $document->id,
                'curriculum_id' => $data['curriculum_id'],
                'education_level_id' => $data['education_level_id'],
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'topic' => $data['topic'],
                'subtopic' => $data['subtopic'] ?? null,
                'question_type' => $item['type'] ?? $data['settings']['question_type'],
                'difficulty' => $item['difficulty'] ?? $data['settings']['difficulty'],
                'question_text' => strip_tags((string) ($item['question'] ?? '')),
                'answer_text' => strip_tags((string) ($item['answer'] ?? '')),
                'explanation' => strip_tags((string) ($item['explanation'] ?? '')),
                'cognitive_level' => $item['cognitive_level'] ?? null,
                'image_asset' => $this->assets->supports(data_get($item, 'image.asset')) ? data_get($item, 'image.asset') : null,
                'image_count' => $this->assets->supports(data_get($item, 'image.asset')) ? max(1, min(10, (int) data_get($item, 'image.count', 1))) : null,
                'source_type' => 'generated',
                'is_active' => true,
            ]);

            foreach (($item['options'] ?? []) as $index => $option) {
                $question->options()->create([
                    'label' => $option['label'] ?? chr(65 + $index),
                    'content' => strip_tags((string) ($option['content'] ?? '')),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }

    private function text(string $key, string $title, string $content): array
    {
        return compact('key', 'title', 'content') + ['type' => 'text'];
    }

    private function list(string $key, string $title, array $content): array
    {
        return compact('key', 'title', 'content') + ['type' => 'list'];
    }

    private function table(string $key, string $title, array $headers, array $content): array
    {
        return compact('key', 'title', 'headers', 'content') + ['type' => 'table'];
    }

    private function questions(string $key, string $title, array $content): array
    {
        return compact('key', 'title', 'content') + ['type' => 'questions'];
    }
}
