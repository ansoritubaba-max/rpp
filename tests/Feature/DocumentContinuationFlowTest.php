<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\Document;
use App\Models\EducationLevel;
use App\Models\Feature;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\Documents\DocumentTemplateResolver;
use App\Services\Documents\RppService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DocumentContinuationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_can_duplicate_rpp_without_spending_ai_quota(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $original = $this->makeRpp($teacher);

        $this->actingAs($teacher)->post(route('documents.duplicate', $original), [
            'title' => 'RPP Pecahan Pertemuan 2',
            'topic' => 'Pecahan Campuran',
            'subtopic' => 'Penjumlahan',
            'meetings' => 2,
            'lesson_date' => '2026-10-15',
        ])->assertRedirect();

        $copy = Document::query()->where('title', 'RPP Pecahan Pertemuan 2')->firstOrFail();
        $this->assertSame('Pecahan Sederhana', $original->fresh()->topic);
        $this->assertSame('Pecahan Campuran', $copy->content_json['material']['topic']);
        $this->assertSame($original->content_json['objectives'], $copy->content_json['objectives']);
        $this->assertSame('2026-10-15', $copy->content_json['identity']['lesson_date']);
        $this->assertSame(2, $copy->metadata_json['meetings']);
        $this->assertDatabaseHas('document_versions', ['document_id' => $copy->id, 'version_number' => 1]);
        $this->assertDatabaseCount('feature_usages', 0);
        $this->get(route('documents.show', $copy))->assertOk()->assertSee('Salin RPP')->assertSee('Tanggal Belajar')->assertSee('Buat ulang');
        $this->get(route('documents.pdf', $copy))->assertOk()->assertHeader('content-type', 'application/pdf');
        $print = $this->get(route('documents.print', $copy))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=rpp_pecahan_pertemuan_2_2026.pdf');
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $print->getContent()));
    }

    public function test_regeneration_changes_only_selected_section_and_consumes_one_regeneration(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $document = $this->makeRpp($teacher);
        $original = $document->content_json;
        $ai = Mockery::mock(AiManager::class);
        $ai->shouldReceive('generate')->once()->andReturn(['content' => ['Tujuan baru yang terukur.']]);
        app()->instance(AiManager::class, $ai);

        $this->actingAs($teacher)->post(route('documents.regenerate', $document), ['section' => 'objectives'])->assertRedirect();

        $changed = $document->fresh()->content_json;
        $this->assertSame(['Tujuan baru yang terukur.'], $changed['objectives']);
        $this->assertSame($original['main_activity'], $changed['main_activity']);
        $this->assertSame($original['assessment'], $changed['assessment']);
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version_number' => 2]);
        $this->assertDatabaseHas('feature_usages', ['feature_id' => $document->feature_id, 'generation_count' => 0, 'regeneration_count' => 1]);
    }

    public function test_failed_regeneration_keeps_document_version_and_quota_unchanged(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $document = $this->makeRpp($teacher);
        $original = $document->content_json;
        $ai = Mockery::mock(AiManager::class);
        $ai->shouldReceive('generate')->once()->andReturn(['content' => []]);
        app()->instance(AiManager::class, $ai);

        $this->actingAs($teacher)->post(route('documents.regenerate', $document), ['section' => 'objectives'])->assertSessionHasErrors('regenerate');

        $this->assertSame($original, $document->fresh()->content_json);
        $this->assertSame(1, $document->versions()->count());
        $this->assertDatabaseCount('feature_usages', 0);
    }

    public function test_nested_ai_rpp_content_is_normalized_before_saving(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $ai = Mockery::mock(AiManager::class);
        $ai->shouldReceive('generate')->once()->andReturn([
            'objectives' => ['Peserta didik memahami pecahan.'],
            'apperception' => ['Guru menunjukkan kue.', 'Siswa menyebutkan bagian kue.'],
            'main_activity' => [['meeting' => 1, 'activities' => ['Mengamati gambar.', 'Membagi kue.']]],
            'assessment' => ['formative' => [['technique' => 'Observasi', 'indicators' => ['Aktif berdiskusi.', 'Menjelaskan jawaban.']]]],
        ]);
        app()->instance(AiManager::class, $ai);

        $document = $this->makeRpp($teacher);
        $content = $document->content_json;

        $this->assertSame('Guru menunjukkan kue.; Siswa menyebutkan bagian kue.', $content['opening'][1]);
        $this->assertSame('Pertemuan 1: Mengamati gambar.; Membagi kue.', $content['main_activity'][0]);
        $this->assertSame(
            [['title' => 'Pertemuan 1', 'steps' => ['Mengamati gambar.', 'Membagi kue.']]],
            \App\Services\Documents\DocumentContentFormatter::activityGroups($content['main_activity']),
        );
        $this->assertSame('Formatif', $content['assessment'][0]['type']);
        $this->assertStringContainsString('Aktif berdiskusi.', $content['assessment'][0]['method']);
    }

    public function test_existing_nested_rpp_can_be_opened_and_downloaded(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $document = $this->makeRpp($teacher);
        $content = $document->content_json;
        $content['opening'][1] = ['Guru menunjukkan kue.', 'Siswa menyebutkan bagian kue.'];
        $content['main_activity'] = [['meeting' => 1, 'activities' => ['Mengamati gambar.', 'Membagi kue.']]];
        $content['assessment'] = ['formative' => [['technique' => 'Observasi', 'indicators' => ['Aktif berdiskusi.']]]];
        $document->update(['content_json' => $content]);

        $this->actingAs($teacher)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSeeText('Pertemuan 1')
            ->assertSeeText('Mengamati gambar.')
            ->assertSeeText('Membagi kue.')
            ->assertSeeText('Aktif berdiskusi.');
        $this->get(route('documents.pdf', $document))->assertOk()->assertHeader('content-type', 'application/pdf');

        $sections = app(DocumentTemplateResolver::class)->sections($document);
        $activity = collect($sections)->firstWhere('key', 'main_activity');
        $this->assertSame('activity_plan', $activity['type']);
        $this->assertSame([['title' => 'Pertemuan 1', 'steps' => ['Mengamati gambar.', 'Membagi kue.']]], $activity['content']);
    }

    public function test_other_teacher_cannot_duplicate_or_regenerate_document(): void
    {
        $owner = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $document = $this->makeRpp($owner);
        $other = User::factory()->create();

        $this->actingAs($other)->post(route('documents.duplicate', $document), [
            'title' => 'Salinan', 'topic' => 'Materi baru', 'meetings' => 1,
        ])->assertForbidden();
        $this->post(route('documents.regenerate', $document), ['section' => 'objectives'])->assertForbidden();
        $this->get(route('documents.print', $document))->assertForbidden();
    }

    private function makeRpp(User $teacher): Document
    {
        $school = $teacher->schools()->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = Grade::query()->where('education_level_id', $level->id)->where('numeric_value', 4)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $level->id)->where('code', 'MAT')->firstOrFail();

        return app(RppService::class)->create($teacher, Feature::query()->where('code', 'rpp')->firstOrFail(), [
            'school_id' => $school->id, 'school_name' => $school->name, 'teacher_name' => $teacher->name,
            'academic_year' => '2026/2027', 'curriculum_id' => $curriculum->id, 'curriculum_name' => $curriculum->name,
            'education_level_id' => $level->id, 'level_name' => $level->name, 'grade_id' => $grade->id,
            'grade_name' => $grade->name, 'grade_numeric' => $grade->numeric_value, 'subject_id' => $subject->id,
            'subject_name' => $subject->name, 'semester' => 1, 'topic' => 'Pecahan Sederhana', 'subtopic' => '',
            'meetings' => 1, 'jp' => 2, 'jp_duration' => 35, 'learning_model' => 'Problem Based Learning',
            'method' => 'Diskusi', 'student_condition' => 'Heterogen', 'reading_ability' => 'normal',
            'facilities' => 'Papan tulis', 'teacher_notes' => '', 'components' => ['objectives'],
        ]);
    }
}
