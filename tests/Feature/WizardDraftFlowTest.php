<?php

namespace Tests\Feature;

use App\Livewire\FeatureGenerator;
use App\Livewire\RppWizard;
use App\Models\Curriculum;
use App\Models\EducationLevel;
use App\Models\User;
use App\Models\WizardDraft;
use App\Services\AI\AiManager;
use App\Services\AI\AllModelsUnavailableException;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WizardDraftFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_rpp_draft_persists_between_component_mounts_and_is_private(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $other = User::factory()->create(['role' => 'guru', 'email_verified_at' => now()]);

        Livewire::actingAs($teacher)->test(RppWizard::class)
            ->set('topic', 'Pecahan campuran untuk kelas empat')
            ->set('teacherNotes', 'Gunakan benda konkret')
            ->call('nextStep')
            ->assertHasErrors(['gradeId']);

        $draft = WizardDraft::query()->where('user_id', $teacher->id)->where('feature_code', 'rpp')->firstOrFail();
        $this->assertSame('Pecahan campuran untuk kelas empat', $draft->payload_json['topic']);
        $this->assertSame('Gunakan benda konkret', $draft->payload_json['teacherNotes']);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('feature_usages', 0);

        Livewire::actingAs($teacher)->test(RppWizard::class)
            ->assertSet('topic', 'Pecahan campuran untuk kelas empat')
            ->assertSet('teacherNotes', 'Gunakan benda konkret');
        Livewire::actingAs($other)->test(RppWizard::class)->assertSet('topic', '');

        $this->actingAs($teacher)->get(route('create.index'))->assertOk()->assertSee('Lanjutkan draf Anda')->assertSee('Pecahan campuran untuk kelas empat');
    }

    public function test_generic_drafts_are_independent_and_can_be_discarded(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'lkpd'])
            ->set('topic', 'Eksperimen air')
            ->set('activityCount', 4);
        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'soal'])
            ->set('topic', 'Bilangan bulat');

        $this->assertDatabaseCount('wizard_drafts', 2);
        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'lkpd'])
            ->assertSet('topic', 'Eksperimen air')
            ->assertSet('activityCount', 4)
            ->call('discardDraft')
            ->assertRedirect(route('generator.create', 'lkpd'));

        $this->assertDatabaseMissing('wizard_drafts', ['user_id' => $teacher->id, 'feature_code' => 'lkpd']);
        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'soal'])
            ->assertSet('topic', 'Bilangan bulat');
        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'lkpd'])
            ->assertSet('topic', '');
    }

    public function test_successful_generation_clears_only_its_draft(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 4)->firstOrFail();
        $subject = $level->subjects()->where('code', 'MAT')->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'lkpd'])
            ->set('topic', 'Draf LKPD terpisah');

        Livewire::actingAs($teacher)->test(RppWizard::class)
            ->set('educationLevelId', $level->id)
            ->set('gradeId', $grade->id)
            ->set('subjectId', $subject->id)
            ->set('curriculumId', $curriculum->id)
            ->set('topic', 'RPP yang selesai')
            ->call('generate')
            ->assertRedirect();

        $this->assertDatabaseMissing('wizard_drafts', ['user_id' => $teacher->id, 'feature_code' => 'rpp']);
        $this->assertDatabaseHas('wizard_drafts', ['user_id' => $teacher->id, 'feature_code' => 'lkpd']);
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_ai_failure_saves_an_rpp_document_draft_without_consuming_quota(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 4)->firstOrFail();
        $subject = $level->subjects()->where('code', 'MAT')->firstOrFail();
        $this->mock(AiManager::class)
            ->shouldReceive('generate')
            ->once()
            ->andThrow(new AllModelsUnavailableException('Semua model AI sedang tidak tersedia.'));

        Livewire::actingAs($teacher)->test(RppWizard::class)
            ->set('gradeId', $grade->id)
            ->set('subjectId', $subject->id)
            ->set('topic', 'Pecahan untuk draf gagal AI')
            ->call('generate')
            ->assertRedirect();

        $this->assertDatabaseHas('documents', ['user_id' => $teacher->id, 'status' => 'draft', 'topic' => 'Pecahan untuk draf gagal AI']);
        $this->assertDatabaseHas('wizard_drafts', ['user_id' => $teacher->id, 'feature_code' => 'rpp']);
        $this->assertDatabaseCount('feature_usages', 0);
        $document = $teacher->documents()->firstOrFail();
        $this->actingAs($teacher)->get(route('documents.show', $document))->assertOk()->assertSee('Lanjutkan draf formulir');
        $this->get(route('documents.pdf', $document))->assertRedirect()->assertSessionHasErrors('document');
    }

    public function test_ai_failure_reuses_generic_document_draft_on_retry(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 4)->firstOrFail();
        $subject = $level->subjects()->where('code', 'MAT')->firstOrFail();
        $this->mock(AiManager::class)
            ->shouldReceive('generate')
            ->twice()
            ->andThrow(new AllModelsUnavailableException('Semua model AI sedang tidak tersedia.'));

        for ($attempt = 0; $attempt < 2; $attempt++) {
            Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'modul_ajar'])
                ->set('gradeId', $grade->id)
                ->set('subjectId', $subject->id)
                ->set('topic', 'Modul draf saat AI gagal')
                ->call('generate')
                ->assertRedirect();
        }

        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseHas('documents', ['user_id' => $teacher->id, 'status' => 'draft', 'topic' => 'Modul draf saat AI gagal']);
        $this->assertDatabaseHas('wizard_drafts', ['user_id' => $teacher->id, 'feature_code' => 'modul_ajar']);
        $this->assertDatabaseCount('feature_usages', 0);
    }

    public function test_successful_retry_replaces_failed_ai_draft_without_leaving_a_stale_card(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 4)->firstOrFail();
        $subject = $level->subjects()->where('code', 'MAT')->firstOrFail();
        $attempts = 0;
        $this->mock(AiManager::class)
            ->shouldReceive('generate')
            ->twice()
            ->andReturnUsing(function () use (&$attempts) {
                if (++$attempts === 1) {
                    throw new AllModelsUnavailableException('Semua model AI sedang tidak tersedia.');
                }

                return null;
            });

        for ($attempt = 0; $attempt < 2; $attempt++) {
            Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'modul_ajar'])
                ->set('gradeId', $grade->id)
                ->set('subjectId', $subject->id)
                ->set('topic', 'Modul pemulihan AI')
                ->call('generate')
                ->assertRedirect();
        }

        $this->assertSame(1, $teacher->documents()->count());
        $this->assertSame(1, $teacher->documents()->onlyTrashed()->count());
        $this->assertDatabaseHas('documents', ['user_id' => $teacher->id, 'status' => 'generated', 'topic' => 'Modul pemulihan AI']);
        $this->assertDatabaseMissing('wizard_drafts', ['user_id' => $teacher->id, 'feature_code' => 'modul_ajar']);
        $this->assertDatabaseHas('feature_usages', ['generation_count' => 1]);
    }
}
