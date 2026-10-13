<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Feature;
use App\Models\User;
use App\Services\AI\AiManager;
use Illuminate\Support\Str;

class RppService
{
    public function __construct(private readonly AiManager $aiManager) {}

    public function create(User $user, Feature $feature, array $data): Document
    {
        $ai = $this->aiManager->generate('rpp', 'rpp_sections', [
            'curriculum' => $data['curriculum_name'],
            'level' => $data['level_name'],
            'vocational_program' => $data['vocational_program_name'] ?? null,
            'grade' => $data['grade_name'],
            'subject' => $data['subject_name'],
            'topic' => $data['topic'],
            'subtopic' => $data['subtopic'] ?? null,
            'meetings' => $data['meetings'],
            'jp' => $data['jp'],
            'model' => $data['learning_model'],
            'reading' => $data['reading_ability'],
            'student_condition' => $data['student_condition'],
            'teacher_notes' => $data['teacher_notes'] ?? null,
            'curriculum_reference' => $data['curriculum_context'] ?? [],
            'source_reference' => $data['source_context'] ?? [],
        ], ['objectives', 'apperception', 'main_activity', 'assessment']);

        $content = $this->buildContent($data, $ai);
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
            'title' => "RPP {$data['subject_name']} — {$data['topic']}",
            'topic' => $data['topic'],
            'subtopic' => $data['subtopic'] ?? null,
            'content_json' => $content,
            'metadata_json' => [
                'meetings' => (int) $data['meetings'],
                'jp' => (int) $data['jp'],
                'jp_duration' => (int) $data['jp_duration'],
                'learning_model' => $data['learning_model'],
                'reading_ability' => $data['reading_ability'],
                'source' => $ai ? 'hybrid-ai' : 'preset-demo',
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

        return $document;
    }

    private function buildContent(array $data, ?array $ai): array
    {
        $topic = $data['topic'];
        $model = $data['learning_model'];
        $lowerGrade = (int) $data['grade_numeric'] <= 3;
        $databaseObjectives = $data['source_context']['objectives'] ?? [];
        if ($databaseObjectives === []) {
            $databaseObjectives = $data['curriculum_context']['objectives'] ?? [];
        }
        if ($databaseObjectives === []) {
            $databaseObjectives = $data['curriculum_context']['outcomes'] ?? [];
        }

        return DocumentContentFormatter::normalizeRpp([
            'identity' => [
                'school' => $data['school_name'],
                'teacher' => $data['teacher_name'],
                'subject' => $data['subject_name'],
                'class' => $data['grade_name'],
                'semester' => $data['semester'],
                'academic_year' => $data['academic_year'],
                'curriculum' => $data['curriculum_name'],
                'vocational_program' => $data['vocational_program_name'] ?? null,
                'time_allocation' => "{$data['meetings']} pertemuan × {$data['jp']} JP ({$data['jp_duration']} menit/JP)",
            ],
            'objectives' => $databaseObjectives ?: ($ai['objectives'] ?? [
                "Peserta didik mampu menjelaskan konsep utama {$topic} dengan bahasa sendiri.",
                "Peserta didik mampu menerapkan konsep {$topic} melalui aktivitas kontekstual.",
                'Peserta didik menunjukkan sikap aktif, teliti, dan bertanggung jawab selama pembelajaran.',
            ]),
            'material' => [
                'topic' => $data['topic'],
                'subtopic' => $data['subtopic'] ?: 'Konsep dasar dan penerapan kontekstual',
                'resources' => 'Buku teks, lingkungan sekitar, media visual, dan lembar aktivitas peserta didik.',
            ],
            'opening' => [
                'Guru memberi salam, memeriksa kesiapan belajar, dan mengajak peserta didik berdoa.',
                $ai['apperception'] ?? "Guru mengaitkan pengalaman sehari-hari peserta didik dengan materi {$topic} melalui pertanyaan singkat.",
                'Guru menyampaikan tujuan dan manfaat pembelajaran dengan bahasa yang mudah dipahami.',
            ],
            'main_activity' => $ai['main_activity'] ?? [
                "Orientasi: peserta didik mengamati contoh nyata yang berkaitan dengan {$topic}.",
                "Eksplorasi: guru memfasilitasi pembelajaran {$model} secara berkelompok dan terarah.",
                'Kolaborasi: peserta didik mendiskusikan temuan, menyelesaikan tugas, dan berbagi peran.',
                'Komunikasi: perwakilan kelompok menyajikan hasil dan kelompok lain memberi tanggapan.',
                'Konfirmasi: guru menguatkan konsep, meluruskan miskonsepsi, dan memberi umpan balik.',
            ],
            'closing' => [
                'Peserta didik bersama guru menyimpulkan pembelajaran.',
                'Peserta didik melakukan refleksi singkat tentang hal yang dipahami dan masih membingungkan.',
                'Guru menyampaikan tindak lanjut, kegiatan berikutnya, dan menutup pembelajaran.',
            ],
            'assessment' => $ai['assessment'] ?? [
                ['type' => 'Diagnostik', 'method' => 'Pertanyaan pemantik dan observasi kesiapan awal.'],
                ['type' => 'Formatif', 'method' => 'Observasi proses, hasil diskusi, dan lembar aktivitas.'],
                ['type' => 'Sumatif', 'method' => 'Tugas individu yang mengukur pemahaman dan penerapan konsep.'],
            ],
            'differentiation' => [
                'support' => $lowerGrade || $data['reading_ability'] !== 'normal'
                    ? 'Gunakan instruksi pendek, font besar, contoh visual, dan pendampingan bertahap.'
                    : 'Berikan contoh tambahan dan panduan bertahap bagi peserta didik yang membutuhkan.',
                'enrichment' => "Peserta didik yang telah tuntas membuat contoh penerapan {$topic} dari lingkungan sekitar.",
                'remedial' => 'Guru melakukan pembelajaran ulang dalam kelompok kecil dengan media konkret dan latihan sederhana.',
            ],
            'reflection' => [
                'student' => 'Apa hal baru yang saya pahami? Bagian mana yang masih ingin saya tanyakan?',
                'teacher' => 'Apakah strategi pembelajaran efektif? Siapa yang memerlukan dukungan lanjutan?',
            ],
        ]);
    }
}
