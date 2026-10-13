<?php

namespace Tests\Feature;

use App\Livewire\FeatureGenerator;
use App\Livewire\RppWizard;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\EducationLevel;
use App\Models\Feature;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Services\Documents\GenericDocumentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentChainFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_can_continue_the_document_chain_without_retyping_context(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $prota = $this->createProta($teacher);

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'promes'])
            ->set('sourceDocumentUuid', $prota->uuid)
            ->assertSet('schoolId', $prota->school_id)
            ->assertSet('topicList', 'Pecahan')
            ->call('generate')
            ->assertRedirect();
        $promes = Document::query()->where('source_document_id', $prota->id)->firstOrFail();
        $this->assertSame('Pecahan', data_get($promes->content_json, 'sections.1.content.0.1'));

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'atp'])
            ->set('sourceDocumentUuid', $promes->uuid)
            ->assertSet('topicList', 'Pecahan')
            ->call('generate')
            ->assertRedirect();
        $atp = Document::query()->where('source_document_id', $promes->id)->firstOrFail();
        $this->assertSame('Pecahan', data_get($atp->content_json, 'sections.2.content.0.2'));

        Livewire::actingAs($teacher)->test(RppWizard::class)
            ->set('sourceDocumentUuid', $atp->uuid)
            ->assertSet('topic', 'Pecahan')
            ->call('generate')
            ->assertRedirect();
        $rpp = Document::query()->where('source_document_id', $atp->id)->firstOrFail();
        $this->assertSame(data_get($atp->content_json, 'sections.2.content.0.1'), data_get($rpp->content_json, 'objectives.0'));

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'lkpd'])
            ->set('sourceDocumentUuid', $rpp->uuid)
            ->call('generate')
            ->assertRedirect();
        $lkpd = Document::query()->where('source_document_id', $rpp->id)->firstOrFail();
        $this->assertSame(data_get($rpp->content_json, 'objectives.0'), data_get($lkpd->content_json, 'sections.1.content.0'));

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'soal'])
            ->set('sourceDocumentUuid', $lkpd->uuid)
            ->call('generate')
            ->assertRedirect();
        $soal = Document::query()->where('source_document_id', $lkpd->id)->firstOrFail();
        $this->assertSame(10, $soal->questions()->count());

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'kisi_kisi'])
            ->set('sourceDocumentUuid', $soal->uuid)
            ->assertSet('sourceMode', 'dokumen')
            ->call('generate')
            ->assertRedirect();
        $kisi = Document::query()->where('source_document_id', $soal->id)->firstOrFail();
        $this->assertCount(10, data_get($kisi->content_json, 'sections.0.content'));
        $this->assertSame($soal->questions()->first()->question_text, data_get($kisi->content_json, 'sections.0.content.0.2'));

        $this->actingAs($teacher)->get(route('documents.show', $prota))
            ->assertOk()->assertSee('Buat Promes');
        $this->get(route('documents.show', $kisi))->assertOk()->assertSee('Dibuat dari:');
    }

    public function test_foreign_or_mismatched_source_is_rejected_before_generation(): void
    {
        $owner = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $other = User::factory()->create(['role' => 'guru', 'email_verified_at' => now()]);
        $foreignProta = $this->createProta($owner);

        Livewire::actingAs($other)->test(FeatureGenerator::class, ['featureCode' => 'promes'])
            ->set('sourceDocumentUuid', $foreignProta->uuid)
            ->assertHasErrors('sourceDocumentUuid');
        $this->assertDatabaseCount('documents', 1);

        $differentSubject = Subject::query()
            ->where('education_level_id', $foreignProta->education_level_id)
            ->whereKeyNot($foreignProta->subject_id)
            ->firstOrFail();
        Livewire::actingAs($owner)->test(FeatureGenerator::class, ['featureCode' => 'promes'])
            ->set('sourceDocumentUuid', $foreignProta->uuid)
            ->set('subjectId', $differentSubject->id)
            ->call('generate')
            ->assertHasErrors('sourceDocumentUuid');
        $this->assertDatabaseCount('documents', 1);
    }

    private function createProta(User $teacher): Document
    {
        $school = $teacher->schools()->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = Grade::query()->where('education_level_id', $level->id)->where('numeric_value', 4)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $level->id)->where('code', 'MAT')->firstOrFail();
        $feature = Feature::query()->where('code', 'prota')->firstOrFail();

        return app(GenericDocumentService::class)->create($teacher, $feature, [
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
            'topic' => 'Pecahan',
            'subtopic' => '',
            'teacher_notes' => '',
            'settings' => ['total_jp' => 108, 'effective_weeks' => 18, 'topic_list' => "Pecahan\nBangun datar"],
        ]);
    }
}
