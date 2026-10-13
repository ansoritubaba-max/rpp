<?php

namespace Tests\Feature;

use App\Livewire\FeatureGenerator;
use App\Models\Curriculum;
use App\Models\EducationLevel;
use App\Models\Feature;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Services\Documents\GenericDocumentService;
use App\Services\Documents\RppService;
use App\Services\Subscription\QuotaService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_can_login_and_open_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'guru@ruangajar.id',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->get('/dashboard')->assertOk()->assertSee('Apa yang ingin');
    }

    public function test_admin_area_is_protected_by_role(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();

        $this->actingAs($teacher)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Ringkasan bisnis');
    }

    public function test_rpp_generation_is_saved_and_consumes_one_quota(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $school = $teacher->schools()->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = Grade::query()->where('education_level_id', $level->id)->where('numeric_value', 4)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $level->id)->where('code', 'MAT')->firstOrFail();

        $document = app(QuotaService::class)->runGeneration(
            $teacher,
            'rpp',
            fn (Feature $feature) => app(RppService::class)->create($teacher, $feature, [
                'school_id' => $school->id, 'school_name' => $school->name, 'teacher_name' => $teacher->name,
                'academic_year' => '2026/2027', 'curriculum_id' => $curriculum->id, 'curriculum_name' => $curriculum->name,
                'education_level_id' => $level->id, 'level_name' => $level->name, 'grade_id' => $grade->id,
                'grade_name' => $grade->name, 'grade_numeric' => $grade->numeric_value, 'subject_id' => $subject->id,
                'subject_name' => $subject->name, 'semester' => 1, 'topic' => 'Pecahan Sederhana', 'subtopic' => '',
                'meetings' => 1, 'jp' => 2, 'jp_duration' => 35, 'learning_model' => 'Problem Based Learning',
                'method' => 'Diskusi', 'student_condition' => 'Heterogen', 'reading_ability' => 'normal',
                'facilities' => 'Papan tulis', 'teacher_notes' => '', 'components' => ['objectives'],
            ]),
        );

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'generated']);
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version_number' => 1]);
        $this->assertDatabaseHas('feature_usages', ['generation_count' => 1]);
        $this->actingAs($teacher)->get(route('documents.show', $document))->assertOk()->assertSee('Pecahan Sederhana');
        $this->get(route('documents.pdf', $document))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_teacher_can_open_generator_hub_and_each_generic_generator(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();

        $this->actingAs($teacher)
            ->get(route('create.index'))
            ->assertOk()
            ->assertSee('Studio perangkat pembelajaran')
            ->assertSee('Modul Ajar')
            ->assertSee('Bank Soal');

        foreach (['modul_ajar', 'silabus', 'lkpd', 'soal', 'kisi_kisi', 'prota', 'promes', 'atp'] as $featureCode) {
            $this->get(route('generator.create', $featureCode))->assertOk();
        }
    }

    public function test_question_generation_creates_document_versions_and_question_bank_items(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $school = $teacher->schools()->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = Grade::query()->where('education_level_id', $level->id)->where('numeric_value', 4)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $level->id)->where('code', 'MAT')->firstOrFail();

        $document = app(QuotaService::class)->runGeneration(
            $teacher,
            'soal',
            fn (Feature $feature) => app(GenericDocumentService::class)->create($teacher, $feature, [
                'school_id' => $school->id,
                'school_name' => $school->name,
                'teacher_name' => $teacher->name,
                'academic_year' => '2026/2027',
                'curriculum_id' => $curriculum->id,
                'curriculum_name' => $curriculum->name,
                'education_level_id' => $level->id,
                'level_name' => $level->name,
                'grade_id' => $grade->id,
                'grade_name' => $grade->name,
                'grade_phase' => $grade->phase,
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'semester' => 1,
                'topic' => 'Pecahan Sederhana',
                'subtopic' => '',
                'teacher_notes' => '',
                'settings' => [
                    'question_type' => 'pilihan_ganda',
                    'question_count' => 5,
                    'difficulty' => 'sedang',
                    'hots' => true,
                    'story_questions' => false,
                    'include_answer' => true,
                    'include_explanation' => true,
                    'include_blueprint' => true,
                ],
            ]),
        );

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'feature_id' => Feature::query()->where('code', 'soal')->value('id')]);
        $this->assertDatabaseCount('questions', 5);
        $this->assertDatabaseCount('question_options', 20);
        $this->assertDatabaseHas('feature_usages', ['feature_id' => $document->feature_id, 'generation_count' => 1]);

        $this->actingAs($teacher)->get(route('questions.index'))->assertOk()->assertSee('Pecahan Sederhana');
        $this->get(route('documents.show', $document))->assertOk()->assertSee('Daftar Soal');
        $this->get(route('documents.pdf', $document))->assertOk()->assertHeader('content-type', 'application/pdf');
        $print = $this->get(route('documents.print', $document))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $print->getContent()));
    }

    public function test_generic_text_section_can_be_edited_as_a_new_version(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $school = $teacher->schools()->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = Grade::query()->where('education_level_id', $level->id)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $level->id)->firstOrFail();

        $document = app(QuotaService::class)->runGeneration(
            $teacher,
            'modul_ajar',
            fn (Feature $feature) => app(GenericDocumentService::class)->create($teacher, $feature, [
                'school_id' => $school->id, 'school_name' => $school->name, 'teacher_name' => $teacher->name,
                'academic_year' => '2026/2027', 'curriculum_id' => $curriculum->id, 'curriculum_name' => $curriculum->name,
                'education_level_id' => $level->id, 'level_name' => $level->name, 'grade_id' => $grade->id,
                'grade_name' => $grade->name, 'grade_phase' => $grade->phase, 'subject_id' => $subject->id,
                'subject_name' => $subject->name, 'semester' => 1, 'topic' => 'Lingkungan Sekitar', 'subtopic' => '',
                'teacher_notes' => '', 'settings' => ['learning_model' => 'Problem Based Learning', 'student_condition' => 'Heterogen'],
            ]),
        );

        $this->actingAs($teacher)->patch(route('documents.update', $document), [
            'section' => 'informasi_umum',
            'content' => 'Informasi umum yang sudah disesuaikan oleh guru.',
        ])->assertRedirect();

        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version_number' => 2]);
        $this->assertSame('Informasi umum yang sudah disesuaikan oleh guru.', data_get($document->fresh()->content_json, 'sections.0.content'));
    }

    public function test_question_generator_livewire_wizard_completes_all_steps(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $school = $teacher->schools()->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = Grade::query()->where('education_level_id', $level->id)->where('numeric_value', 4)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $level->id)->where('code', 'MAT')->firstOrFail();

        Livewire::actingAs($teacher)
            ->test(FeatureGenerator::class, ['featureCode' => 'soal'])
            ->set('schoolId', $school->id)
            ->set('curriculumId', $curriculum->id)
            ->set('educationLevelId', $level->id)
            ->set('gradeId', $grade->id)
            ->set('subjectId', $subject->id)
            ->call('nextStep')
            ->assertSet('step', 2)
            ->set('topic', 'Keliling dan Luas Persegi')
            ->set('questionCount', 5)
            ->set('hots', true)
            ->call('nextStep')
            ->assertSet('step', 3)
            ->call('generate')
            ->assertRedirect();

        $this->assertDatabaseCount('questions', 5);
        $this->assertDatabaseHas('documents', ['topic' => 'Keliling dan Luas Persegi', 'status' => 'generated']);
    }

    public function test_every_generic_document_preset_can_be_generated(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $school = $teacher->schools()->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = Grade::query()->where('education_level_id', $level->id)->where('numeric_value', 4)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $level->id)->where('code', 'MAT')->firstOrFail();
        $payload = [
            'school_id' => $school->id, 'school_name' => $school->name, 'teacher_name' => $teacher->name,
            'academic_year' => '2026/2027', 'curriculum_id' => $curriculum->id, 'curriculum_name' => $curriculum->name,
            'education_level_id' => $level->id, 'level_name' => $level->name, 'grade_id' => $grade->id,
            'grade_name' => $grade->name, 'grade_phase' => $grade->phase, 'subject_id' => $subject->id,
            'subject_name' => $subject->name, 'semester' => 1, 'topic' => 'Bangun Datar', 'subtopic' => '',
            'teacher_notes' => '',
            'settings' => [
                'learning_model' => 'Problem Based Learning', 'student_condition' => 'Heterogen',
                'activity_count' => 3, 'difficulty' => 'sedang', 'effective_weeks' => 18,
                'question_count' => 10, 'cognitive_level' => 'C2-C4', 'total_jp' => 108,
                'topic_list' => "Persegi\nPersegi Panjang\nSegitiga", 'objective_count' => 5,
                'learning_outcome' => 'Peserta didik memahami sifat dan penerapan bangun datar.',
            ],
        ];

        foreach (['modul_ajar', 'silabus', 'lkpd', 'kisi_kisi', 'prota', 'promes', 'atp'] as $featureCode) {
            $document = app(QuotaService::class)->runGeneration(
                $teacher,
                $featureCode,
                fn (Feature $feature) => app(GenericDocumentService::class)->create($teacher, $feature, $payload),
            );

            $this->assertNotEmpty(data_get($document->content_json, 'sections'));
            if ($featureCode === 'lkpd') {
                $activities = collect(data_get($document->content_json, 'sections'))
                    ->firstWhere('key', 'aktivitas')['content'];
                $this->assertCount(3, array_unique($activities));
            }
            $this->actingAs($teacher)->get(route('documents.show', $document))->assertOk();
            $print = $this->get(route('documents.print', $document))->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('inline;', $print->headers->get('content-disposition'));

            $content = $document->content_json;
            $firstSection = &$content['sections'][0];
            $firstSection['content'] = match ($firstSection['type']) {
                'text' => implode("\n", array_fill(0, 45, 'Uraian pembelajaran lengkap yang perlu dibaca guru dan peserta didik sebelum kegiatan dimulai.')),
                'list' => array_fill(0, 65, 'Langkah pembelajaran yang rinci untuk memastikan seluruh kegiatan dapat diikuti dengan baik.'),
                'table' => array_fill(0, 65, array_fill(0, count($firstSection['headers']), 'Materi pembelajaran dan indikator yang harus dicapai.')),
            };
            unset($firstSection);
            $document->update(['content_json' => $content]);

            $longPrint = $this->get(route('documents.print', $document))->assertOk();
            $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $longPrint->getContent()), $featureCode.' harus mencetak semua halaman.');
        }

        $this->assertDatabaseCount('documents', 7);
        $this->assertDatabaseCount('document_versions', 7);
    }
}
