<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Feature;
use App\Models\User;
use App\Services\Documents\SchoolLogoService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolLetterheadFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_teacher_can_upload_preview_replace_and_remove_private_school_logos(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $school = $teacher->schools()->firstOrFail();

        $this->actingAs($teacher)->put(route('schools.update'), $this->schoolPayload([
            'logo_left' => UploadedFile::fake()->image('sekolah.png', 240, 240),
            'logo_right' => UploadedFile::fake()->image('yayasan.jpg', 240, 240),
        ]))->assertRedirect();

        $school->refresh();
        $this->assertNotNull($school->logo_left);
        $this->assertNotNull($school->logo_right);
        Storage::disk('local')->assertExists([$school->logo_left, $school->logo_right]);
        $this->get(route('schools.logo', 'left'))->assertOk()->assertHeader('content-type', 'image/png');
        $this->assertStringStartsWith('data:image/png;base64,', app(SchoolLogoService::class)->dataUri($school, 'left'));

        $oldLeft = $school->logo_left;
        $oldRight = $school->logo_right;
        $this->put(route('schools.update'), $this->schoolPayload([
            'logo_left' => UploadedFile::fake()->image('baru.png', 240, 240),
            'remove_logo_right' => '1',
        ]))->assertRedirect();

        $school->refresh();
        Storage::disk('local')->assertMissing([$oldLeft, $oldRight]);
        Storage::disk('local')->assertExists($school->logo_left);
        $this->assertNull($school->logo_right);
        $this->get(route('schools.logo', 'right'))->assertNotFound();
    }

    public function test_invalid_file_is_rejected_and_another_account_cannot_read_logo(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $school = $teacher->schools()->firstOrFail();

        $this->actingAs($teacher)->put(route('schools.update'), $this->schoolPayload([
            'logo_left' => UploadedFile::fake()->image('valid.png', 240, 240),
        ]))->assertRedirect();
        $original = $school->fresh()->logo_left;

        $this->put(route('schools.update'), $this->schoolPayload([
            'logo_left' => UploadedFile::fake()->createWithContent('berbahaya.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ]))->assertSessionHasErrors('logo_left');
        $this->assertSame($original, $school->fresh()->logo_left);

        $otherTeacher = User::factory()->create(['role' => 'guru', 'status' => 'active']);
        $this->actingAs($otherTeacher)->get(route('schools.logo', 'left'))->assertNotFound();
        $this->actingAs($teacher)->get(route('schools.logo', 'left'))->assertOk();
    }

    public function test_pdf_renders_with_uploaded_logo(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $school = $teacher->schools()->firstOrFail();
        $this->actingAs($teacher)->put(route('schools.update'), $this->schoolPayload([
            'logo_left' => UploadedFile::fake()->image('sekolah.png', 240, 240),
        ]))->assertRedirect();

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $teacher->id,
            'school_id' => $school->id,
            'feature_id' => Feature::query()->where('code', 'rpp')->value('id'),
            'title' => 'RPP Uji Kop',
            'topic' => 'Pecahan',
            'content_json' => [
                'identity' => ['school' => $school->name, 'subject' => 'Matematika', 'class' => 'Kelas 4', 'semester' => 1, 'academic_year' => '2026/2027'],
                'objectives' => ['Memahami pecahan.'],
            ],
            'metadata_json' => [],
            'status' => 'generated',
        ]);

        $response = $this->get(route('documents.pdf', $document))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('/Subtype /Image', $response->getContent());
    }

    private function schoolPayload(array $values = []): array
    {
        return ['name' => 'SD Negeri Harapan Bangsa', 'address' => 'Jl. Pendidikan No. 12'] + $values;
    }
}
