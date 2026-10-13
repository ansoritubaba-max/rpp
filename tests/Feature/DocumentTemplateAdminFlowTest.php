<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\EducationLevel;
use App\Models\Feature;
use App\Models\User;
use App\Services\Documents\DocumentTemplateResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentTemplateAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seed_creates_standard_templates_and_admin_can_manage_a_specific_template(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $feature = Feature::query()->where('code', 'rpp')->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();

        $this->assertSame(9, DocumentTemplate::query()->count());

        $response = $this->actingAs($admin)->post(route('admin.templates.store'), $this->templatePayload($feature->id, $curriculum->id, $level->id));
        $template = DocumentTemplate::query()->where('name', 'RPP Merdeka SD')->firstOrFail();
        $response->assertRedirect(route('admin.templates.edit', $template));

        $this->post(route('admin.templates.sections.store', $template), [
            'section_key' => 'catatan_guru',
            'title' => 'Catatan Guru',
            'sort_order' => 99,
            'source_type' => 'static',
            'preset_content' => 'Catatan ini tampil dari preset template.',
            'ai_task_key' => null,
            'is_required' => false,
            'is_enabled' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('template_sections', ['template_id' => $template->id, 'section_key' => 'catatan_guru']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_template.created', 'entity_id' => $template->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'template_section.created']);
        $this->get(route('admin.templates.index'))->assertOk()->assertSee('RPP Merdeka SD');
        $this->get(route('admin.templates.edit', $template))->assertOk()->assertSee('Catatan Guru');
    }

    public function test_resolver_prefers_specific_template_and_pdf_uses_configured_sections(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $feature = Feature::query()->where('code', 'rpp')->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();
        $grade = $level->grades()->firstOrFail();
        $subject = $level->subjects()->firstOrFail();

        $template = DocumentTemplate::query()->create($this->templatePayload($feature->id, $curriculum->id, $level->id));
        $template->sections()->createMany([
            ['section_key' => 'assessment', 'title' => 'A. Penilaian Khusus', 'sort_order' => 1, 'source_type' => 'database', 'is_required' => true, 'is_enabled' => true],
            ['section_key' => 'objectives', 'title' => 'B. Sasaran Belajar', 'sort_order' => 2, 'source_type' => 'hybrid', 'is_required' => true, 'is_enabled' => true],
            ['section_key' => 'opening', 'title' => 'Disembunyikan', 'sort_order' => 3, 'source_type' => 'hybrid', 'is_required' => false, 'is_enabled' => false],
            ['section_key' => 'catatan', 'title' => 'C. Catatan Tetap', 'sort_order' => 4, 'source_type' => 'static', 'preset_content' => 'Isi preset aman.', 'is_required' => false, 'is_enabled' => true],
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $teacher->id,
            'school_id' => $teacher->schools()->value('id'),
            'feature_id' => $feature->id,
            'curriculum_id' => $curriculum->id,
            'education_level_id' => $level->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026/2027',
            'semester' => 1,
            'title' => 'RPP Matematika — Pecahan',
            'topic' => 'Pecahan',
            'content_json' => [
                'identity' => ['school' => 'SD Negeri Harapan Bangsa', 'subject' => $subject->name, 'class' => $grade->name, 'semester' => 1, 'academic_year' => '2026/2027'],
                'objectives' => ['Memahami pecahan.'],
                'opening' => ['Berdoa.'],
                'assessment' => [['type' => 'Formatif', 'method' => 'Observasi.']],
            ],
            'metadata_json' => [],
            'status' => 'generated',
        ]);

        $resolver = app(DocumentTemplateResolver::class);
        $this->assertTrue($resolver->resolve($document)->is($template));
        $sections = $resolver->sections($document, $template->load('sections'));
        $this->assertSame(['assessment', 'objectives', 'catatan'], array_column($sections, 'key'));
        $this->assertSame('A. Penilaian Khusus', $sections[0]['title']);
        $this->assertSame('Isi preset aman.', $sections[2]['content']);

        $template->sections()->where('section_key', 'assessment')->update(['source_type' => 'static', 'preset_content' => 'Asesmen mengikuti kebijakan sekolah.']);
        $staticSections = $resolver->sections($document, $template->load('sections'));
        $this->assertSame('text', $staticSections[0]['type']);
        $this->assertSame('Asesmen mengikuti kebijakan sekolah.', $staticSections[0]['content']);

        $this->actingAs($teacher)->get(route('documents.pdf', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_activating_same_scope_replaces_previous_template_and_teacher_is_forbidden(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $feature = Feature::query()->where('code', 'rpp')->firstOrFail();
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SD')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.templates.store'), $this->templatePayload($feature->id, $curriculum->id, $level->id))->assertRedirect();
        $first = DocumentTemplate::query()->where('name', 'RPP Merdeka SD')->firstOrFail();
        $payload = $this->templatePayload($feature->id, $curriculum->id, $level->id);
        $payload['name'] = 'RPP Merdeka SD Baru';
        $this->post(route('admin.templates.store'), $payload)->assertRedirect();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue(DocumentTemplate::query()->where('name', 'RPP Merdeka SD Baru')->firstOrFail()->is_active);
        $this->actingAs($teacher)->get(route('admin.templates.index'))->assertForbidden();
    }

    private function templatePayload(int $featureId, int $curriculumId, int $levelId): array
    {
        return [
            'name' => 'RPP Merdeka SD',
            'feature_id' => $featureId,
            'curriculum_id' => $curriculumId,
            'education_level_id' => $levelId,
            'paper_size' => 'a4',
            'orientation' => 'landscape',
            'margin_top' => 15,
            'margin_right' => 16,
            'margin_bottom' => 17,
            'margin_left' => 18,
            'font_family' => 'DejaVu Sans',
            'font_size' => 10,
            'line_height' => 1.4,
            'is_active' => true,
        ];
    }
}
