<?php

namespace Tests\Feature;

use App\Livewire\FeatureGenerator;
use App\Models\Document;
use App\Models\Grade;
use App\Models\QuestionSet;
use App\Models\Subject;
use App\Models\User;
use App\Services\Documents\QuestionAssetLibrary;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LowGradeVisualQuestionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_low_reader_soal_automatically_uses_internal_images_across_document_bank_and_pdf(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $grade = Grade::query()->where('numeric_value', 1)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $grade->education_level_id)->where('code', 'MAT')->firstOrFail();

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'soal'])
            ->set('gradeId', $grade->id)
            ->set('subjectId', $subject->id)
            ->set('topic', 'Berhitung')
            ->set('readingAbility', 'belum_lancar')
            ->set('imageAsset', 'cat')
            ->set('imageCount', 3)
            ->set('questionCount', 5)
            ->call('generate')
            ->assertRedirect();

        $document = Document::query()->where('feature_id', $this->featureId('soal'))->latest('id')->firstOrFail();
        $first = data_get($document->content_json, 'sections.0.content.0');
        $this->assertSame(['asset' => 'cat', 'count' => 3], $first['image']);
        $this->assertSame('Hitung jumlah kucing pada gambar.', $first['question']);
        $this->assertCount(3, $first['options']);
        $this->assertSame('3', $first['options'][1]['content']);
        $this->assertSame('visual-preset', $document->metadata_json['source']);
        $this->assertDatabaseHas('questions', ['document_id' => $document->id, 'image_asset' => 'cat', 'image_count' => 3]);
        $this->actingAs($teacher)->get(route('documents.show', $document))->assertOk()->assertSee('cat.svg')->assertSee('text-lg leading-9');
        $this->get(route('questions.index'))->assertOk()->assertSee('cat.svg');
        $question = $document->questions()->firstOrFail();
        $this->post(route('question-sets.store'), ['title' => 'Latihan Gambar', 'question_ids' => [$question->id]])->assertRedirect();
        $set = QuestionSet::query()->where('title', 'Latihan Gambar')->firstOrFail();
        $this->get(route('question-sets.show', $set))->assertOk()->assertSee('cat.svg');
        $this->get(route('documents.pdf', $document))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_low_reader_lkpd_uses_short_instructions(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $grade = Grade::query()->where('numeric_value', 2)->firstOrFail();
        $subject = Subject::query()->where('education_level_id', $grade->education_level_id)->where('code', 'MAT')->firstOrFail();

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'lkpd'])
            ->set('gradeId', $grade->id)
            ->set('subjectId', $subject->id)
            ->set('topic', 'Lingkungan Sekolah')
            ->set('readingAbility', 'belum_lancar')
            ->call('generate')
            ->assertRedirect();

        $document = Document::query()->where('feature_id', $this->featureId('lkpd'))->latest('id')->firstOrFail();
        $this->assertSame('Dengar arahan guru. Lihat gambar atau contoh. Kerjakan satu per satu.', data_get($document->content_json, 'sections.0.content'));
        $this->assertStringContainsString('Amati gambar atau benda', data_get($document->content_json, 'sections.2.content.0'));
        $this->actingAs($teacher)->get(route('documents.show', $document))->assertOk()->assertSee('text-lg leading-9');
    }

    public function test_invalid_asset_cannot_be_used_and_every_library_asset_exists(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $grade = Grade::query()->where('numeric_value', 1)->firstOrFail();

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'soal'])
            ->set('gradeId', $grade->id)
            ->set('topic', 'Berhitung')
            ->set('imageAsset', '../secret')
            ->call('generate')
            ->assertHasErrors('imageAsset');
        $this->assertDatabaseCount('documents', 0);

        $library = app(QuestionAssetLibrary::class);
        $this->assertCount(10, $library->keys());
        foreach ($library->keys() as $key) {
            $this->assertFileExists(public_path('images/question-assets/'.$key.'.svg'));
            $this->assertStringStartsWith('data:image/svg+xml;base64,', $library->dataUri($key));
        }
        $this->assertNull($library->dataUri('../secret'));
    }

    private function featureId(string $code): int
    {
        return (int) \App\Models\Feature::query()->where('code', $code)->value('id');
    }
}
