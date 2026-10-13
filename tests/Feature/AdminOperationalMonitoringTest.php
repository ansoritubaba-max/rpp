<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\AiUsageLog;
use App\Models\Document;
use App\Models\Feature;
use App\Models\OperationalEvent;
use App\Models\User;
use App\Services\Documents\DocumentTemplateResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminOperationalMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_brand_uses_supplied_logo_and_admin_status_is_not_hard_coded(): void
    {
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();

        $this->get('/')->assertOk()->assertSee('rg.png')->assertSee('Logo RuangAjar');
        $this->get(route('login'))->assertOk()->assertSee('rg.png');
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('rg.png')->assertSee('Perlu perhatian')->assertSee('Backup belum tersedia');

        Backup::query()->create(['type' => 'database_and_files', 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Tidak ada insiden terkini');
    }

    public function test_failed_and_rate_limited_login_are_recorded_without_credentials(): void
    {
        $email = 'unknown-ops-test@example.test';
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.44']);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'private-password'])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame(5, OperationalEvent::query()->where('type', 'login_failed')->count());
        $this->assertSame(1, OperationalEvent::query()->where('type', 'login_rate_limited')->count());
        $event = OperationalEvent::query()->firstOrFail();
        $this->assertNotNull($event->ip_fingerprint);
        $this->assertNotSame('192.0.2.44', $event->ip_fingerprint);
        $this->assertStringNotContainsString($email, json_encode($event->getAttributes()));
        $this->assertStringNotContainsString('private-password', json_encode($event->getAttributes()));

        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Login dibatasi');
        RateLimiter::clear('login:'.hash('sha256', $email.'|192.0.2.44'));
        RateLimiter::clear('login-ip:'.hash('sha256', '192.0.2.44'));
    }

    public function test_pdf_failure_is_recorded_and_teacher_gets_a_simple_message(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $teacher->id,
            'school_id' => $teacher->schools()->value('id'),
            'feature_id' => Feature::query()->where('code', 'rpp')->value('id'),
            'title' => 'Dokumen Uji PDF',
            'topic' => 'Pecahan',
            'content_json' => ['identity' => ['school' => 'Sekolah Uji'], 'sections' => []],
            'status' => 'generated',
        ]);
        $resolver = Mockery::mock(DocumentTemplateResolver::class);
        $resolver->shouldReceive('resolve')->once()->andThrow(new RuntimeException('private diagnostic'));
        app()->instance(DocumentTemplateResolver::class, $resolver);

        $this->actingAs($teacher)->from(route('documents.show', $document))
            ->get(route('documents.pdf', $document))
            ->assertRedirect(route('documents.show', $document))
            ->assertSessionHasErrors('pdf');

        $this->assertDatabaseHas('operational_events', ['type' => 'pdf_failed', 'user_id' => $teacher->id, 'document_id' => $document->id]);
        $event = OperationalEvent::query()->where('type', 'pdf_failed')->firstOrFail();
        $this->assertStringNotContainsString('private diagnostic', json_encode($event->getAttributes()));
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('PDF gagal');
    }

    public function test_ai_monitoring_filters_by_teacher_and_groups_model_cost(): void
    {
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $other = User::factory()->create(['role' => 'guru']);
        foreach ([$teacher, $other] as $user) {
            AiUsageLog::query()->create([
                'user_id' => $user->id,
                'feature_id' => Feature::query()->where('code', 'rpp')->value('id'),
                'input_tokens' => 100,
                'output_tokens' => 50,
                'estimated_cost' => '0.002500',
                'status' => 'success',
                'duration_ms' => 100,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.ai.index', ['user' => $teacher->id, 'period' => 'all']))
            ->assertOk()->assertSee('Ringkasan provider', false);
        $this->assertSame(1, (int) $response->viewData('stats')->total);
        $this->assertSame(1, (int) $response->viewData('modelSummary')->first()->request_count);
        $this->assertSame(100, (int) $response->viewData('modelSummary')->first()->input_tokens);
    }
}
