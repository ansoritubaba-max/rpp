<?php

namespace Tests\Feature;

use App\Livewire\FeatureGenerator;
use App\Livewire\RppWizard;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\EducationLevel;
use App\Models\LearningOutcome;
use App\Models\User;
use App\Services\Academic\AcademicContextService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OfficialCurriculumSeeder;
use Database\Seeders\VerifiedCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VocationalProgramFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_smk_programs_are_manageable_with_traceable_starter_curriculum(): void
    {
        $level = EducationLevel::query()->where('code', 'SMK')->firstOrFail();
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $this->assertSame([10, 11, 12], $level->grades()->pluck('numeric_value')->all());
        $this->assertTrue($level->vocationalPrograms()->where('code', 'TKJ')->exists());
        $this->assertTrue($level->vocationalPrograms()->where('code', 'TKR')->exists());
        $this->assertTrue($level->vocationalPrograms()->where('code', 'TBSM')->exists());
        $this->assertTrue($level->vocationalPrograms()->where('code', 'DKF')->exists());
        $this->assertGreaterThan(700, \App\Models\CurriculumSubject::query()->count());
        $this->assertGreaterThan(700, LearningOutcome::query()->where('source_kind', 'official')->count());
        $this->assertDatabaseCount('learning_objectives', 101);
        $this->assertDatabaseCount('competencies', 14);
        $this->assertSame(128, $level->vocationalPrograms()->whereHas('curriculumSubjects.learningOutcomes', fn ($query) => $query->where('source_kind', 'official'))->count());
        $this->assertSame(0, $level->vocationalPrograms()->where('code', 'DKF')->firstOrFail()
            ->curriculumSubjects()->count());

        $tkj = $level->vocationalPrograms()->where('code', 'TKJ')->firstOrFail();
        $tkjSubject = $level->subjects()->where('code', 'TKJ')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 11)->firstOrFail();
        $merdeka = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $contextService = app(AcademicContextService::class);
        $semesterOne = $contextService->forSelection($merdeka->id, $level->id, $grade->id, $tkjSubject->id, 1, $tkj->id);
        $semesterTwo = $contextService->forSelection($merdeka->id, $level->id, $grade->id, $tkjSubject->id, 2, $tkj->id);
        $this->assertCount(1, $semesterOne['outcomes']);
        $this->assertContains('https://bpmpbabel.kemendikdasmen.go.id/PPID/wp-content/uploads/2025/07/KepKaBSKAP-046_2025-ttg-CP.pdf#page=869', $semesterOne['sources']);
        $this->assertSame($semesterOne, $semesterTwo);

        $smp = EducationLevel::query()->where('code', 'SMP')->firstOrFail();
        $k13 = Curriculum::query()->where('code', 'K13')->firstOrFail();
        $k13Context = $contextService->forSelection(
            $k13->id,
            $smp->id,
            $smp->grades()->where('numeric_value', 7)->value('id'),
            $smp->subjects()->where('code', 'MAT')->value('id'),
            2,
        );
        $this->assertCount(2, $k13Context['competencies']);

        $beforeContexts = \App\Models\CurriculumSubject::query()->count();
        $beforeOutcomes = LearningOutcome::query()->count();
        $this->seed([VerifiedCurriculumSeeder::class, OfficialCurriculumSeeder::class]);
        $this->assertSame($beforeContexts, \App\Models\CurriculumSubject::query()->count());
        $this->assertSame($beforeOutcomes, LearningOutcome::query()->count());
        $this->actingAs($admin)->get(route('admin.academic.content.index', [
            'vocational_program' => $tkj->id,
        ]))->assertOk()->assertSee('Buka sumber resmi')->assertSee('Ringkasan CP, bukan kutipan lengkap.');

        $this->actingAs($admin)->post(route('admin.academic.vocational-programs.store', $level), [
            'code' => 'ANIM', 'name' => 'Animasi', 'is_active' => 1, 'sort_order' => 8,
        ])->assertRedirect();
        $program = $level->vocationalPrograms()->where('code', 'ANIM')->firstOrFail();
        $this->patch(route('admin.academic.vocational-programs.update', [$level, $program]), [
            'code' => 'ANIM', 'name' => 'Animasi Digital', 'is_active' => 1, 'sort_order' => 8,
        ])->assertRedirect();
        $this->post(route('admin.academic.subjects.store', $level), [
            'code' => 'ANIM1', 'name' => 'Dasar Animasi', 'vocational_program_id' => $program->id,
            'is_religious' => 0, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('subjects', ['education_level_id' => $level->id, 'code' => 'ANIM1', 'vocational_program_id' => $program->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academic.vocational_program_updated', 'entity_id' => $program->id]);
        $this->get(route('admin.academic.levels.show', $level))->assertOk()->assertSee('Animasi Digital')->assertSee('Dasar Animasi');
    }

    public function test_curriculum_context_is_isolated_by_smk_program(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SMK')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 10)->firstOrFail();
        $subject = $level->subjects()->where('code', 'MAT')->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $tkj = $level->vocationalPrograms()->where('code', 'TKJ')->firstOrFail();
        $tkr = $level->vocationalPrograms()->where('code', 'TKR')->firstOrFail();
        $beforeCount = LearningOutcome::query()->count();

        foreach ([[$tkj, 'Matematika untuk jaringan komputer.'], [$tkr, 'Matematika untuk kendaraan ringan.']] as [$program, $text]) {
            $this->actingAs($admin)->post(route('admin.academic.outcomes.store'), [
                'curriculum_id' => $curriculum->id, 'education_level_id' => $level->id,
                'vocational_program_id' => $program->id, 'grade_id' => $grade->id,
                'subject_id' => $subject->id, 'semester' => 1, 'phase' => 'E',
                'element' => 'Bilangan', 'content' => $text,
            ])->assertRedirect();
        }

        $context = app(AcademicContextService::class);
        $this->assertSame(['Matematika untuk jaringan komputer.'], $context->forSelection($curriculum->id, $level->id, $grade->id, $subject->id, 1, $tkj->id)['outcomes']);
        $this->assertSame(['Matematika untuk kendaraan ringan.'], $context->forSelection($curriculum->id, $level->id, $grade->id, $subject->id, 1, $tkr->id)['outcomes']);
        $this->assertSame(2, LearningOutcome::query()->count() - $beforeCount);
    }

    public function test_each_education_level_has_sourced_merdeka_and_k13_starter_content(): void
    {
        $contextService = app(AcademicContextService::class);
        $merdeka = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $k13 = Curriculum::query()->where('code', 'K13')->firstOrFail();

        foreach (['SD' => 2, 'MI' => 2, 'SMP' => 7, 'MTS' => 7, 'SMA' => 10, 'MA' => 10, 'SMK' => 10] as $levelCode => $k13Grade) {
            $level = EducationLevel::query()->where('code', $levelCode)->firstOrFail();
            $bahasa = $level->subjects()->where('code', 'BIND')->firstOrFail();
            $math = $level->subjects()->where('code', 'MAT')->firstOrFail();

            foreach ($level->grades as $grade) {
                foreach ([1, 2] as $semester) {
                    $context = $contextService->forSelection($merdeka->id, $level->id, $grade->id, $bahasa->id, $semester);
                    $this->assertCount(1, $context['outcomes'], "{$levelCode} kelas {$grade->numeric_value} semester {$semester} belum memiliki CP resmi.");
                    $this->assertCount(2, $context['objectives']);
                    $mathContext = $contextService->forSelection($merdeka->id, $level->id, $grade->id, $math->id, $semester);
                    $this->assertCount(1, $mathContext['outcomes']);
                    $this->assertCount(1, $mathContext['objectives']);
                }
            }

            $grade = $level->grades()->where('numeric_value', $k13Grade)->firstOrFail();
            $this->assertCount(2, $contextService->forSelection($k13->id, $level->id, $grade->id, $math->id, 1)['competencies']);
        }

        $this->assertGreaterThan(700, LearningOutcome::query()->where('source_kind', 'official')->whereNotNull('source_url')->count());
    }

    public function test_teacher_selects_program_and_cannot_mix_subjects_from_other_programs(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SMK')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 10)->firstOrFail();
        $tkj = $level->vocationalPrograms()->where('code', 'TKJ')->firstOrFail();
        $tkrSubject = $level->subjects()->where('code', 'TKR')->firstOrFail();
        $tkjSubject = $level->subjects()->where('code', 'TKJ')->firstOrFail();

        Livewire::actingAs($teacher)->test(RppWizard::class)
            ->set('educationLevelId', $level->id)
            ->set('vocationalProgramId', $tkj->id)
            ->set('gradeId', $grade->id)
            ->set('subjectId', $tkrSubject->id)
            ->call('nextStep')
            ->assertHasErrors(['subjectId']);

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'lkpd'])
            ->set('educationLevelId', $level->id)
            ->set('vocationalProgramId', $tkj->id)
            ->set('gradeId', $grade->id)
            ->set('subjectId', $tkrSubject->id)
            ->call('nextStep')
            ->assertHasErrors(['subjectId']);

        Livewire::actingAs($teacher)->test(RppWizard::class)
            ->set('educationLevelId', $level->id)
            ->set('vocationalProgramId', $tkj->id)
            ->set('gradeId', $grade->id)
            ->set('subjectId', $tkjSubject->id)
            ->set('topic', 'Perencanaan jaringan lokal')
            ->call('generate')
            ->assertRedirect();

        $document = Document::query()->where('topic', 'Perencanaan jaringan lokal')->firstOrFail();
        $this->assertSame($tkj->id, $document->vocational_program_id);
        $this->assertSame($tkj->name, $document->content_json['identity']['vocational_program']);
        $this->actingAs($teacher)->get(route('documents.show', $document))->assertOk()->assertSee('Konsentrasi Keahlian')->assertSee($tkj->name);
        $this->get(route('documents.pdf', $document))->assertOk()->assertHeader('content-type', 'application/pdf');

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'lkpd'])
            ->set('educationLevelId', $level->id)
            ->set('vocationalProgramId', $tkj->id)
            ->set('gradeId', $grade->id)
            ->set('subjectId', $tkjSubject->id)
            ->set('topic', 'Latihan jaringan lokal')
            ->call('generate')
            ->assertRedirect();

        $worksheet = Document::query()->where('topic', 'Latihan jaringan lokal')->firstOrFail();
        $this->assertSame($tkj->id, $worksheet->vocational_program_id);
        $this->actingAs($teacher)->get(route('documents.show', $worksheet))->assertOk()->assertSee($tkj->name);
        $this->get(route('documents.pdf', $worksheet))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
