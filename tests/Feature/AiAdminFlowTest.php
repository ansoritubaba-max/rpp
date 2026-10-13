<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Feature;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AllModelsUnavailableException;
use App\Services\Subscription\QuotaService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VerifiedQwenRoutesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_manage_provider_without_exposing_api_key(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.ai.providers.store'), [
            'name' => 'OpenRouter Utama',
            'provider_type' => 'openrouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'secret-key-1234',
            'status' => 'active',
            'timeout_seconds' => 45,
            'notes' => 'Akun produksi.',
        ])->assertRedirect(route('admin.ai.providers.index'));

        $provider = AiProvider::query()->where('name', 'OpenRouter Utama')->firstOrFail();
        $this->assertNotSame('secret-key-1234', $provider->getRawOriginal('encrypted_api_key'));
        $this->assertSame('secret-key-1234', Crypt::decryptString($provider->getRawOriginal('encrypted_api_key')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.provider_created', 'entity_id' => $provider->id]);
        $auditPayload = DB::table('audit_logs')->where('action', 'ai.provider_created')->value('after_json');
        $this->assertStringNotContainsString('secret-key-1234', (string) $auditPayload);

        $encryptedKey = $provider->getRawOriginal('encrypted_api_key');
        $this->put(route('admin.ai.providers.update', $provider), [
            'name' => 'OpenRouter Utama',
            'provider_type' => 'openrouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => '',
            'status' => 'active',
            'timeout_seconds' => 60,
            'notes' => 'Akun produksi.',
        ])->assertRedirect(route('admin.ai.providers.index'));

        $this->assertSame($encryptedKey, $provider->fresh()->getRawOriginal('encrypted_api_key'));
        $this->get(route('admin.ai.providers.index'))->assertOk()->assertSee('••••••••1234');
    }

    public function test_admin_can_create_model_and_configure_feature_routing(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $provider = AiProvider::query()->create([
            'name' => 'Provider Test', 'provider_type' => 'custom', 'base_url' => 'https://ai.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);

        $this->actingAs($admin)->post(route('admin.ai.models.store'), [
            'provider_id' => $provider->id,
            'name' => 'Model Cepat',
            'model_key' => 'fast-model',
            'priority' => 1,
            'status' => 'active',
            'max_output_tokens' => 4096,
            'timeout_seconds' => 30,
            'estimated_input_cost' => 0.15,
            'estimated_output_cost' => 0.60,
            'internal_daily_limit' => 1000,
            'cooldown_seconds' => 60,
        ])->assertRedirect(route('admin.ai.models.index'));

        $model = AiModel::query()->where('model_key', 'fast-model')->firstOrFail();
        $this->get(route('admin.ai.models.index'))
            ->assertOk()
            ->assertSee('Model Cepat');
        $feature = Feature::query()->where('code', 'rpp')->firstOrFail();
        $this->put(route('admin.ai.routing.update'), [
            'routes' => [$feature->id => [$model->id => ['enabled' => true, 'priority' => 1]]],
        ])->assertRedirect();

        $this->assertDatabaseHas('ai_routes', ['feature_id' => $feature->id, 'ai_model_id' => $model->id, 'priority' => 1, 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.model_created', 'entity_id' => $model->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.routing_updated', 'entity_id' => $feature->id]);
    }

    public function test_ai_manager_fails_over_tracks_cost_and_logs_cache_hits(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $provider = AiProvider::query()->create([
            'name' => 'Provider Test', 'provider_type' => 'custom', 'base_url' => 'https://ai.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $first = $provider->models()->create([
            'name' => 'Model Pertama', 'model_key' => 'model-one', 'priority' => 1, 'status' => 'active',
            'estimated_input_cost' => 1, 'estimated_output_cost' => 2, 'cooldown_seconds' => 120,
        ]);
        $second = $provider->models()->create([
            'name' => 'Model Cadangan', 'model_key' => 'model-two', 'priority' => 2, 'status' => 'active',
            'estimated_input_cost' => 1, 'estimated_output_cost' => 2, 'cooldown_seconds' => 120,
        ]);
        $feature = Feature::query()->where('code', 'rpp')->firstOrFail();
        $feature->aiModels()->attach([
            $first->id => ['priority' => 1, 'is_active' => true],
            $second->id => ['priority' => 2, 'is_active' => true],
        ]);
        Http::fakeSequence()
            ->push(['error' => ['message' => 'provider unavailable']], 500)
            ->push([
                'choices' => [['message' => ['content' => json_encode(['title' => 'RPP Pecahan'])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]);

        $this->actingAs($teacher);
        $result = app(AiManager::class)->generate('rpp', 'unique-rpp-task', ['topic' => 'Pecahan'], ['title']);
        $this->assertSame('RPP Pecahan', $result['title']);
        $this->assertDatabaseHas('ai_model_health', ['ai_model_id' => $first->id, 'status' => 'cooldown', 'failure_count' => 1]);
        $this->assertDatabaseHas('ai_model_health', ['ai_model_id' => $second->id, 'status' => 'healthy', 'failure_count' => 0]);
        $this->assertDatabaseHas('ai_usage_logs', ['model_id' => $first->id, 'status' => 'failed', 'error_code' => 'HTTP_500']);
        $this->assertDatabaseHas('ai_usage_logs', ['model_id' => $second->id, 'status' => 'success', 'estimated_cost' => 0.000200]);

        $cached = app(AiManager::class)->generate('rpp', 'unique-rpp-task', ['topic' => 'Pecahan'], ['title']);
        $this->assertSame($result, $cached);
        $this->assertDatabaseHas('ai_usage_logs', ['feature_id' => $feature->id, 'cache_hit' => true, 'status' => 'success']);
        Http::assertSentCount(2);
    }

    public function test_reset_health_does_not_claim_a_model_has_succeeded(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $provider = AiProvider::query()->create([
            'name' => 'Provider Belum Teruji', 'provider_type' => 'custom', 'base_url' => 'https://untested.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create(['name' => 'Belum Pernah Berhasil', 'model_key' => 'untested', 'status' => 'active']);
        $model->health()->create(['status' => 'cooldown', 'failure_count' => 2, 'last_error' => 'Respons tidak valid', 'cooldown_until' => now()->addMinute()]);

        $this->actingAs($admin)->post(route('admin.ai.models.health.reset', $model))->assertRedirect();

        $this->assertDatabaseHas('ai_model_health', ['ai_model_id' => $model->id, 'status' => 'unknown', 'last_success_at' => null]);
        $this->get(route('admin.ai.models.index'))->assertOk()->assertSee('Belum pernah berhasil');
    }

    public function test_failover_continues_after_http_error_and_invalid_json_until_third_model_succeeds(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'Provider Berantai', 'provider_type' => 'custom', 'base_url' => 'https://chain.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $first = $provider->models()->create(['name' => 'Pertama', 'model_key' => 'first', 'status' => 'active', 'cooldown_seconds' => 60]);
        $second = $provider->models()->create(['name' => 'Kedua', 'model_key' => 'second', 'status' => 'active', 'cooldown_seconds' => 60]);
        $third = $provider->models()->create(['name' => 'Ketiga', 'model_key' => 'third', 'status' => 'active', 'cooldown_seconds' => 60]);
        Feature::query()->where('code', 'silabus')->firstOrFail()->aiModels()->attach([
            $first->id => ['priority' => 1, 'is_active' => true],
            $second->id => ['priority' => 2, 'is_active' => true],
            $third->id => ['priority' => 3, 'is_active' => true],
        ]);
        Http::fakeSequence()
            ->push(['error' => ['message' => 'sementara tidak tersedia']], 503)
            ->push(['choices' => [['message' => ['content' => 'bukan json']]]])
            ->push(['choices' => [['message' => ['content' => json_encode($this->validSilabus())]]]]);

        $result = app(AiManager::class)->generate('silabus', 'silabus_sections', ['topic' => 'Pecahan unik'], ['sections']);

        $this->assertSame('Pembelajaran terarah.', $result['sections'][0]['content']);
        $this->assertDatabaseHas('ai_usage_logs', ['model_id' => $first->id, 'status' => 'failed', 'error_code' => 'HTTP_503']);
        $this->assertDatabaseHas('ai_usage_logs', ['model_id' => $second->id, 'status' => 'failed', 'error_code' => 'INVALID_RESPONSE']);
        $this->assertDatabaseHas('ai_usage_logs', ['model_id' => $third->id, 'status' => 'success']);
        Http::assertSentCount(3);
    }

    public function test_ai_manager_uses_completion_token_limit_for_gpt_6_luna(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'OpenAI Test', 'provider_type' => 'openai', 'base_url' => 'https://api.openai.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create([
            'name' => 'GPT-6 Luna', 'model_key' => 'gpt-6-luna', 'priority' => 1, 'status' => 'active',
            'max_output_tokens' => 2048, 'cooldown_seconds' => 60,
        ]);
        Feature::query()->where('code', 'rpp')->firstOrFail()->aiModels()->attach($model->id, [
            'priority' => 1, 'is_active' => true,
        ]);
        Http::fake(['api.openai.test/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['ringkasan' => 'Fotosintesis.'])]]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10],
        ])]);

        $result = app(AiManager::class)->generate('rpp', 'luna-payload-test', ['tema' => 'Fotosintesis'], ['ringkasan']);

        $this->assertSame('Fotosintesis.', $result['ringkasan']);
        Http::assertSent(fn ($request) => $request['model'] === 'gpt-6-luna'
            && $request['max_completion_tokens'] === 2048
            && $request['reasoning_effort'] === 'none'
            && ! isset($request['max_tokens'])
            && ! isset($request['temperature']));
    }

    public function test_openrouter_qwen_disables_reasoning_to_preserve_document_output_tokens(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'OpenRouter Test', 'provider_type' => 'openrouter', 'base_url' => 'https://openrouter.test/api/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create([
            'name' => 'Qwen 3.5 9B', 'model_key' => 'qwen/qwen3.5-9b', 'status' => 'active',
            'max_output_tokens' => 4096,
        ]);
        Feature::query()->where('code', 'silabus')->firstOrFail()->aiModels()->attach($model->id, [
            'priority' => 1, 'is_active' => true,
        ]);
        Http::fake(['openrouter.test/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($this->validSilabus())]]],
        ])]);

        app(AiManager::class)->generate('silabus', 'silabus_sections', ['topic' => 'Qwen payload test'], ['sections']);

        Http::assertSent(fn ($request) => $request['model'] === 'qwen/qwen3.5-9b'
            && $request['reasoning'] === ['enabled' => false]
            && $request['max_tokens'] === 4096);
    }

    public function test_qwen_probe_accepts_rpp_without_cache_or_route_and_records_real_test(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'OpenRouter Uji', 'provider_type' => 'openrouter', 'base_url' => 'https://qwen-rpp.test/api/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create(['name' => 'Qwen', 'model_key' => 'qwen/qwen3.5-9b', 'status' => 'active', 'max_output_tokens' => 4096]);
        Http::fake(['qwen-rpp.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'objectives' => ['Menjelaskan perubahan wujud air.'],
            'apperception' => 'Guru mengajak siswa mengamati embun di kelas.',
            'main_activity' => ['Siswa mengamati air menguap dan mencatat hasilnya.'],
            'assessment' => [['type' => 'Formatif', 'method' => 'Catatan pengamatan siswa.']],
        ])]]]])]);

        $result = app(AiManager::class)->probe($model, 'rpp', 'rpp_sections', ['topic' => 'Penguapan'],
            ['objectives', 'apperception', 'main_activity', 'assessment']);

        $this->assertSame('Menjelaskan perubahan wujud air.', $result['objectives'][0]);
        $this->assertDatabaseHas('ai_usage_logs', ['model_id' => $model->id, 'status' => 'success', 'is_probe' => true]);
        $this->assertDatabaseCount('ai_content_cache', 0);
        Http::assertSent(function ($request) {
            $body = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

            return $body['format']['objectives'][0] !== ''
                && $body['format']['assessment'][0]['type'] === 'Formatif'
                && $request['reasoning'] === ['enabled' => false];
        });
    }

    public function test_qwen_probe_accepts_complete_module_with_template_shape(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'OpenRouter Modul', 'provider_type' => 'openrouter', 'base_url' => 'https://qwen-module.test/api/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create(['name' => 'Qwen', 'model_key' => 'qwen/qwen3.5-flash-02-23', 'status' => 'active', 'max_output_tokens' => 4096]);
        $sections = [
            ['key' => 'informasi_umum', 'title' => 'Informasi Umum', 'type' => 'text', 'content' => 'Pengamatan penguapan air.'],
            ['key' => 'kompetensi_awal', 'title' => 'Kompetensi Awal', 'type' => 'list', 'content' => ['Mengenal wujud air.']],
            ['key' => 'tujuan', 'title' => 'Tujuan', 'type' => 'list', 'content' => ['Menjelaskan penguapan.']],
            ['key' => 'pemahaman_bermakna', 'title' => 'Pemahaman', 'type' => 'text', 'content' => 'Air dapat berubah wujud.'],
            ['key' => 'pertanyaan_pemantik', 'title' => 'Pertanyaan', 'type' => 'list', 'content' => ['Mengapa pakaian dapat kering?']],
            ['key' => 'kegiatan', 'title' => 'Kegiatan', 'type' => 'list', 'content' => ['Pendahuluan: guru menunjukkan air.', 'Inti: siswa mengamati penguapan.', 'Penutup: siswa menyimpulkan.']],
            ['key' => 'asesmen', 'title' => 'Asesmen', 'type' => 'table', 'headers' => ['Tahap', 'Teknik', 'Instrumen'], 'content' => [['Formatif', 'Observasi', 'Catatan siswa']]],
            ['key' => 'tindak_lanjut', 'title' => 'Tindak Lanjut', 'type' => 'list', 'content' => ['Remedial: amati ulang.', 'Pengayaan: cari contoh lain.', 'Refleksi: tulis pemahaman.']],
        ];
        Http::fake(['qwen-module.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['sections' => $sections])]]]])]);

        $result = app(AiManager::class)->probe($model, 'modul_ajar', 'modul_ajar_sections', ['topic' => 'Penguapan'], ['sections']);

        $this->assertCount(8, $result['sections']);
        $this->assertDatabaseHas('ai_usage_logs', ['model_id' => $model->id, 'status' => 'success', 'is_probe' => true]);
        Http::assertSent(function ($request) {
            $body = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

            return count($body['format']['sections'] ?? []) === 8
                && $body['format']['sections'][6]['key'] === 'asesmen'
                && $request['reasoning'] === ['enabled' => false];
        });
    }

    public function test_failed_probe_is_logged_without_disabling_other_document_routes(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'Provider Probe', 'provider_type' => 'custom', 'base_url' => 'https://probe.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create(['name' => 'Model Probe', 'model_key' => 'probe-model', 'status' => 'active']);
        $model->health()->create(['status' => 'healthy', 'last_success_at' => now(), 'failure_count' => 0]);
        Http::fake(['probe.test/*' => Http::response(['choices' => [['message' => ['content' => '{"sections":[]}']]]])]);

        try {
            app(AiManager::class)->probe($model, 'atp', 'atp_sections', ['topic' => 'Pecahan'], ['sections']);
            $this->fail('Uji model dengan respons tidak valid harus gagal.');
        } catch (AllModelsUnavailableException) {
            $this->assertDatabaseHas('ai_usage_logs', [
                'model_id' => $model->id, 'status' => 'failed', 'is_probe' => true, 'error_code' => 'INVALID_RESPONSE',
            ]);
            $this->assertDatabaseHas('ai_model_health', ['ai_model_id' => $model->id, 'status' => 'healthy', 'failure_count' => 0]);
        }
    }

    public function test_only_live_verified_qwen_combinations_are_added_to_document_routing(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'OpenRouter Routing', 'provider_type' => 'openrouter', 'base_url' => 'https://routing.test/api/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $nineB = $provider->models()->create(['name' => 'Qwen 9B', 'model_key' => 'qwen/qwen3.5-9b', 'status' => 'active']);
        $flash = $provider->models()->create(['name' => 'Qwen Flash', 'model_key' => 'qwen/qwen3.5-flash-02-23', 'status' => 'active']);
        $this->seed(VerifiedQwenRoutesSeeder::class);
        $this->seed(VerifiedQwenRoutesSeeder::class);

        $rpp = Feature::query()->where('code', 'rpp')->firstOrFail();
        $module = Feature::query()->where('code', 'modul_ajar')->firstOrFail();
        $atp = Feature::query()->where('code', 'atp')->firstOrFail();
        $blueprint = Feature::query()->where('code', 'kisi_kisi')->firstOrFail();
        $this->assertDatabaseHas('ai_routes', ['feature_id' => $rpp->id, 'ai_model_id' => $nineB->id, 'priority' => 3]);
        $this->assertDatabaseHas('ai_routes', ['feature_id' => $rpp->id, 'ai_model_id' => $flash->id, 'priority' => 4]);
        $this->assertDatabaseHas('ai_routes', ['feature_id' => $module->id, 'ai_model_id' => $nineB->id, 'priority' => 3]);
        $this->assertDatabaseHas('ai_routes', ['feature_id' => $atp->id, 'ai_model_id' => $nineB->id, 'priority' => 3]);
        $this->assertDatabaseHas('ai_routes', ['feature_id' => $blueprint->id, 'ai_model_id' => $nineB->id, 'priority' => 3]);
        $this->assertDatabaseMissing('ai_routes', ['feature_id' => $module->id, 'ai_model_id' => $flash->id]);
        $this->assertDatabaseMissing('ai_routes', ['feature_id' => $atp->id, 'ai_model_id' => $flash->id]);
        $this->assertDatabaseMissing('ai_routes', ['feature_id' => $blueprint->id, 'ai_model_id' => $flash->id]);
        $this->assertSame(5, $rpp->aiModels()->count() + $module->aiModels()->count()
            + $atp->aiModels()->count() + $blueprint->aiModels()->count());
    }

    public function test_invalid_nested_ai_output_triggers_failover_and_light_repair_is_cached(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $provider = AiProvider::query()->create([
            'name' => 'Provider Struktur', 'provider_type' => 'custom', 'base_url' => 'https://structure.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $first = $provider->models()->create(['name' => 'Struktur Salah', 'model_key' => 'bad', 'priority' => 1, 'status' => 'active', 'cooldown_seconds' => 60]);
        $second = $provider->models()->create(['name' => 'Struktur Baik', 'model_key' => 'good', 'priority' => 2, 'status' => 'active', 'cooldown_seconds' => 60]);
        Feature::query()->where('code', 'prota')->firstOrFail()->aiModels()->attach([
            $first->id => ['priority' => 1, 'is_active' => true],
            $second->id => ['priority' => 2, 'is_active' => true],
        ]);
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['content' => json_encode(['sections' => [[
                'key' => 'tabel', 'title' => 'Tabel', 'type' => 'table',
                'headers' => ['Materi', 'Tujuan'], 'content' => [['Pecahan']],
            ]]])]]]])
            ->push(['choices' => [['message' => ['content' => "```json\n".json_encode(['data' => ['sections' => [[
                'key' => 'tujuan', 'title' => 'Tujuan Pembelajaran', 'content' => "- Memahami pecahan\n- Membandingkan pecahan",
            ]]]], JSON_UNESCAPED_UNICODE)."\n```"]]]]);

        $this->actingAs($teacher);
        $result = app(AiManager::class)->generate('prota', 'custom_sections', ['topic' => 'Pecahan'], ['sections']);

        $this->assertSame('list', $result['sections'][0]['type']);
        $this->assertSame(['Memahami pecahan', 'Membandingkan pecahan'], $result['sections'][0]['content']);
        $this->assertDatabaseHas('ai_model_health', ['ai_model_id' => $first->id, 'status' => 'cooldown']);
        $this->assertDatabaseHas('ai_model_health', ['ai_model_id' => $second->id, 'status' => 'healthy']);
        $this->assertSame($result, app(AiManager::class)->generate('prota', 'custom_sections', ['topic' => 'Pecahan'], ['sections']));
        Http::assertSentCount(2);
    }

    public function test_all_models_with_invalid_content_fail_instead_of_saving_empty_ai_document(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'Provider Rusak', 'provider_type' => 'custom', 'base_url' => 'https://invalid.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create(['name' => 'Rusak', 'model_key' => 'invalid', 'priority' => 1, 'status' => 'active', 'cooldown_seconds' => 60]);
        Feature::query()->where('code', 'rpp')->firstOrFail()->aiModels()->attach($model->id, ['priority' => 1, 'is_active' => true]);
        Http::fake(['invalid.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'objectives' => [], 'apperception' => 'Pembuka', 'main_activity' => ['Kegiatan'], 'assessment' => [],
        ])]]]])]);

        $this->expectException(AllModelsUnavailableException::class);
        app(AiManager::class)->generate('rpp', 'rpp_sections', ['topic' => 'Pecahan'], ['objectives', 'apperception', 'main_activity', 'assessment']);
    }

    public function test_lkpd_markdown_blob_fails_over_to_structured_model_and_prompt_contains_required_shape(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'Provider LKPD', 'provider_type' => 'custom', 'base_url' => 'https://worksheet.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $first = $provider->models()->create(['name' => 'Markdown', 'model_key' => 'markdown', 'status' => 'active', 'cooldown_seconds' => 60]);
        $second = $provider->models()->create(['name' => 'Terstruktur', 'model_key' => 'structured', 'status' => 'active', 'cooldown_seconds' => 60]);
        $feature = Feature::query()->where('code', 'lkpd')->firstOrFail();
        $feature->aiModels()->attach([
            $first->id => ['priority' => 1, 'is_active' => true],
            $second->id => ['priority' => 2, 'is_active' => true],
        ]);
        $invalid = ['sections' => [[
            'key' => 'lembar_kegiatan_peserta_didik', 'title' => 'LKPD', 'type' => 'text',
            'content' => "## Kegiatan 1\nTuliskan jawabanmu.",
        ]]];
        $valid = ['sections' => [
                ['key' => 'petunjuk', 'title' => 'Petunjuk', 'type' => 'text', 'content' => 'Baca teks lalu kerjakan tugas.'],
                ['key' => 'tujuan', 'title' => 'Tujuan', 'type' => 'list', 'content' => ['Menjelaskan isi cerita.']],
                ['key' => 'aktivitas', 'title' => 'Aktivitas', 'type' => 'list', 'content' => [
                    'Tuliskan nama tokoh utama dan bukti dari cerita yang dibaca.',
                    'Simpulkan amanat cerita dan jelaskan alasan berdasarkan peristiwa.',
                ]],
                ['key' => 'hasil', 'title' => 'Hasil', 'type' => 'table', 'headers' => ['Kegiatan', 'Jawaban'],
                    'content' => [['Kegiatan 1', ''], ['Kegiatan 2', '']]],
        ]];
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['content' => json_encode($invalid)]]]])
            ->push(['choices' => [['message' => ['content' => json_encode($valid)]]]]);

        $result = app(AiManager::class)->generate('lkpd', 'lkpd_sections', [
            'topic' => 'Cerita Nusantara', 'settings' => ['activity_count' => 2],
        ], ['sections']);

        $this->assertSame(['petunjuk', 'tujuan', 'aktivitas', 'hasil'], array_column($result['sections'], 'key'));
        $this->assertDatabaseHas('ai_usage_logs', ['feature_id' => $feature->id, 'model_id' => $first->id, 'status' => 'failed', 'error_code' => 'INVALID_RESPONSE']);
        $this->assertDatabaseHas('ai_usage_logs', ['feature_id' => $feature->id, 'model_id' => $second->id, 'status' => 'success']);
        Http::assertSent(function ($request) {
            $body = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

            return array_column($body['format']['sections'] ?? [], 'key') === ['petunjuk', 'tujuan', 'aktivitas', 'hasil'];
        });
    }

    public function test_all_model_failures_keep_diagnostics_without_consuming_teacher_quota(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $provider = AiProvider::query()->create([
            'name' => 'Provider Uji Gagal', 'provider_type' => 'custom', 'base_url' => 'https://failed.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create([
            'name' => 'Model Tidak Valid', 'model_key' => 'invalid', 'status' => 'active', 'cooldown_seconds' => 60,
        ]);
        $feature = Feature::query()->where('code', 'silabus')->firstOrFail();
        $feature->aiModels()->attach($model->id, ['priority' => 1, 'is_active' => true]);
        Http::fake(['failed.test/*' => Http::response(['choices' => [['message' => ['content' => '{"sections":[]}' ]]]])]);

        $this->actingAs($teacher);
        try {
            app(QuotaService::class)->runGeneration($teacher, 'silabus', fn () => app(AiManager::class)
                ->generate('silabus', 'silabus_sections', ['topic' => 'Bahasa Pemrograman'], ['sections']));
            $this->fail('Respons AI tidak valid seharusnya tidak menghasilkan dokumen.');
        } catch (AllModelsUnavailableException) {
            $this->assertDatabaseHas('ai_usage_logs', [
                'feature_id' => $feature->id, 'model_id' => $model->id, 'status' => 'failed',
            ]);
            $this->assertDatabaseHas('ai_model_health', ['ai_model_id' => $model->id, 'status' => 'cooldown']);
            $this->assertDatabaseCount('feature_usages', 0);
        }
    }

    public function test_silabus_request_explicitly_describes_the_expected_section_shape(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'Provider Silabus', 'provider_type' => 'custom', 'base_url' => 'https://silabus.test/v1',
            'api_key' => 'test-key', 'status' => 'active', 'timeout_seconds' => 30,
        ]);
        $model = $provider->models()->create(['name' => 'Model Silabus', 'model_key' => 'silabus-model', 'status' => 'active']);
        Feature::query()->where('code', 'silabus')->firstOrFail()->aiModels()->attach($model->id, ['priority' => 1, 'is_active' => true]);
        $content = json_encode(['sections' => [
            ['title' => 'Rasional', 'content' => 'Pembelajaran terarah.'],
            ['key' => 'silabus', 'title' => 'Pemetaan', 'type' => 'table',
                'headers' => ['Materi', 'Capaian', 'Tujuan', 'Kegiatan', 'Asesmen', 'Alokasi', 'Sumber'],
                'content' => [['Pecahan', 'Capaian', 'Tujuan', 'Latihan', 'Observasi', '2 JP', 'Buku']]],
        ]]);
        Http::fake(['silabus.test/*' => Http::response(['choices' => [['message' => ['content' => $content]]]])]);

        $result = app(AiManager::class)->generate('silabus', 'silabus_sections', ['topic' => 'Bahasa Pemrograman'], ['sections']);

        $this->assertSame('rasional', $result['sections'][0]['key']);
        Http::assertSent(function ($request) {
            $body = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

            return $body['format']['sections'][1]['key'] === 'silabus'
                && count($body['format']['sections'][1]['headers']) === 7;
        });
    }

    public function test_teacher_cannot_access_ai_admin_pages(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();

        $this->actingAs($teacher)->get(route('admin.ai.index'))->assertForbidden();
        $this->get(route('admin.ai.providers.index'))->assertForbidden();
        $this->get(route('admin.ai.models.index'))->assertForbidden();
        $this->get(route('admin.ai.routing.index'))->assertForbidden();
    }

    private function validSilabus(): array
    {
        return ['sections' => [
            ['key' => 'rasional', 'title' => 'Rasional', 'type' => 'text', 'content' => 'Pembelajaran terarah.'],
            ['key' => 'silabus', 'title' => 'Pemetaan', 'type' => 'table',
                'headers' => ['Materi', 'Capaian', 'Tujuan', 'Kegiatan', 'Asesmen', 'Alokasi', 'Sumber'],
                'content' => [['Pecahan', 'Capaian', 'Tujuan', 'Latihan', 'Observasi', '2 JP', 'Buku']]],
        ]];
    }
}
