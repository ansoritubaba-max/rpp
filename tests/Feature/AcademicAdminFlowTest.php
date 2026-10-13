<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Curriculum;
use App\Models\EducationLevel;
use App\Models\Feature;
use App\Models\LearningOutcome;
use App\Models\User;
use App\Services\Academic\AcademicContextService;
use App\Services\Documents\RppService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_manage_academic_master_data(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SMK')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.academic.levels.update', $level), [
            'code' => 'SMK',
            'name' => 'Sekolah Menengah Kejuruan',
            'type' => 'school',
            'sort_order' => 7,
        ])->assertRedirect();

        $this->patch(route('admin.academic.grades.update', [$level, $level->grades()->where('numeric_value', 10)->firstOrFail()]), [
            'name' => 'Kelas 10', 'numeric_value' => 10, 'phase' => 'E', 'sort_order' => 10,
        ])->assertRedirect();
        $this->post(route('admin.academic.subjects.store', $level), [
            'code' => 'PPLG', 'name' => 'Pengembangan Perangkat Lunak dan Gim', 'is_religious' => false, 'is_active' => true,
        ])->assertRedirect();
        $this->post(route('admin.academic.years.store'), [
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('grades', ['education_level_id' => $level->id, 'numeric_value' => 10, 'phase' => 'E']);
        $this->assertDatabaseHas('subjects', ['education_level_id' => $level->id, 'code' => 'PPLG', 'is_active' => true]);
        $this->assertFalse(AcademicYear::query()->where('name', '2026/2027')->firstOrFail()->is_active);
        $this->assertTrue(AcademicYear::query()->where('name', '2027/2028')->firstOrFail()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academic.level_updated', 'entity_id' => $level->id]);
        $this->get(route('admin.academic.index'))->assertOk()->assertSee('Sekolah Menengah Kejuruan');
        $this->get(route('admin.academic.levels.show', $level))->assertOk()->assertSee('Pengembangan Perangkat Lunak dan Gim');
    }

    public function test_saved_learning_objective_is_used_by_rpp_builder(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 4)->firstOrFail();
        $subject = $level->subjects()->where('code', 'MAT')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.academic.outcomes.store'), [
            'curriculum_id' => $curriculum->id,
            'education_level_id' => $level->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'semester' => 1,
            'phase' => 'B',
            'element' => 'Bilangan',
            'content' => 'Peserta didik memahami pecahan dan hubungannya dengan bilangan desimal.',
        ])->assertRedirect();
        $outcome = LearningOutcome::query()->where('content', 'Peserta didik memahami pecahan dan hubungannya dengan bilangan desimal.')->firstOrFail();
        $this->post(route('admin.academic.objectives.store', $outcome), [
            'code' => 'TP-1',
            'content' => 'Peserta didik mampu membandingkan dua pecahan berpenyebut berbeda.',
            'sort_order' => 1,
        ])->assertRedirect();

        $context = app(AcademicContextService::class)->forSelection($curriculum->id, $level->id, $grade->id, $subject->id, 1);
        $this->assertSame('Peserta didik mampu membandingkan dua pecahan berpenyebut berbeda.', $context['objectives'][0]);

        $document = app(RppService::class)->create($teacher, Feature::query()->where('code', 'rpp')->firstOrFail(), [
            'school_id' => $teacher->schools()->value('id'),
            'school_name' => $teacher->schools()->value('name'),
            'teacher_name' => $teacher->name,
            'academic_year' => '2026/2027',
            'curriculum_id' => $curriculum->id,
            'curriculum_name' => $curriculum->name,
            'education_level_id' => $level->id,
            'level_name' => $level->name,
            'grade_id' => $grade->id,
            'grade_name' => $grade->name,
            'grade_numeric' => $grade->numeric_value,
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'semester' => 1,
            'curriculum_context' => $context,
            'topic' => 'Pecahan',
            'subtopic' => 'Membandingkan pecahan',
            'meetings' => 1,
            'jp' => 2,
            'jp_duration' => 35,
            'learning_model' => 'Problem Based Learning',
            'reading_ability' => 'normal',
            'student_condition' => 'Heterogen',
            'teacher_notes' => '',
        ]);

        $this->assertSame($context['objectives'], $document->content_json['objectives']);
        $this->assertTrue($document->metadata_json['curriculum_database']);
        $this->assertSame($context['sources'], $document->metadata_json['curriculum_sources']);
        $this->get(route('admin.academic.content.index'))->assertOk()->assertSee('Bilangan')->assertSee('TP-1');
    }

    public function test_teacher_cannot_access_academic_admin_pages(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();

        $this->actingAs($teacher)->get(route('admin.academic.index'))->assertForbidden();
        $this->get(route('admin.academic.content.index'))->assertForbidden();
    }
}
