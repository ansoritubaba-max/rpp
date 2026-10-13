<?php

namespace Tests\Feature;

use App\Jobs\CreateBackupJob;
use App\Jobs\GenerateDocumentPdfJob;
use App\Models\AiContentCache;
use App\Models\AiModelHealth;
use App\Models\AiProvider;
use App\Models\AiModel;
use App\Models\Document;
use App\Models\DocumentExport;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Backup\BackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackgroundWorkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_backup_is_queued_only_once_while_pending(): void
    {
        Bus::fake();
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $first = app(BackupService::class)->enqueue($admin);
        $second = app(BackupService::class)->enqueue($admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('queued', $first->status);
        $this->assertSame(1, DB::table('backups')->count());
        Bus::assertDispatched(CreateBackupJob::class, 1);
    }

    public function test_cleanup_removes_only_expired_records_and_old_temporary_backup_files(): void
    {
        Storage::fake('local');
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        foreach ([-1, 1] as $days) {
            DB::table('otp_codes')->insert([
                'user_id' => $teacher->id,
                'purpose' => $days < 0 ? 'expired' : 'valid',
                'code_hash' => Hash::make('123456'),
                'expires_at' => now()->addDays($days),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            AiContentCache::query()->create([
                'cache_hash' => hash('sha256', "cache-{$days}"),
                'task_key' => 'rpp',
                'input_signature_json' => [],
                'output_json' => [],
                'expires_at' => now()->addDays($days),
            ]);
        }

        $provider = AiProvider::query()->create([
            'name' => 'Test', 'provider_type' => 'openai', 'base_url' => 'https://example.test', 'api_key' => 'test',
        ]);
        $model = AiModel::query()->create(['provider_id' => $provider->id, 'name' => 'Test', 'model_key' => 'test']);
        $health = AiModelHealth::query()->create([
            'ai_model_id' => $model->id, 'status' => 'cooldown', 'cooldown_until' => now()->subMinute(),
        ]);

        $old = 'backups/tmp/123e4567-e89b-12d3-a456-426614174000.sql';
        $fresh = 'backups/tmp/123e4567-e89b-12d3-a456-426614174001.sql';
        $unrelated = 'backups/tmp/keep.txt';
        foreach ([$old, $fresh, $unrelated] as $path) {
            Storage::disk('local')->put($path, 'temporary');
        }
        touch(Storage::disk('local')->path($old), now()->subDays(2)->timestamp);
        touch(Storage::disk('local')->path($unrelated), now()->subDays(2)->timestamp);

        $this->artisan('maintenance:cleanup')->assertExitCode(0);

        $this->assertSame(1, DB::table('otp_codes')->count());
        $this->assertSame(1, AiContentCache::query()->count());
        $this->assertSame('unknown', $health->fresh()->status);
        $this->assertFalse(Storage::disk('local')->exists($old));
        $this->assertTrue(Storage::disk('local')->exists($fresh));
        $this->assertTrue(Storage::disk('local')->exists($unrelated));
    }

    public function test_subscription_sync_expires_old_and_activates_due_pending(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $plan = Plan::query()->firstOrFail();
        $expired = Subscription::query()->create([
            'user_id' => $teacher->id, 'plan_id' => $plan->id,
            'starts_at' => now()->subDays(31), 'expires_at' => now()->subDay(), 'status' => 'active',
        ]);
        $pending = Subscription::query()->create([
            'user_id' => $teacher->id, 'plan_id' => $plan->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDays(30), 'status' => 'pending',
        ]);
        $future = Subscription::query()->create([
            'user_id' => $teacher->id, 'plan_id' => $plan->id,
            'starts_at' => now()->addDays(31), 'expires_at' => now()->addDays(61), 'status' => 'pending',
        ]);

        $this->artisan('subscriptions:sync-status')->assertExitCode(0);

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('active', $pending->fresh()->status);
        $this->assertSame('pending', $future->fresh()->status);
    }

    public function test_large_pdf_can_be_queued_downloaded_privately_and_cleaned_after_expiry(): void
    {
        Storage::fake('local');
        Bus::fake();
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $other = User::factory()->create(['role' => 'guru']);
        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $teacher->id,
            'school_id' => $teacher->schools()->value('id'),
            'feature_id' => Feature::query()->where('code', 'rpp')->value('id'),
            'title' => 'PDF Antrean', 'topic' => 'Pecahan',
            'content_json' => ['identity' => ['school' => 'Sekolah Uji'], 'sections' => []],
            'status' => 'generated',
        ]);

        $this->actingAs($other)->post(route('documents.exports.store', $document))->assertForbidden();
        $this->actingAs($teacher)->post(route('documents.exports.store', $document))->assertRedirect();
        $this->post(route('documents.exports.store', $document))->assertRedirect();
        $export = DocumentExport::query()->firstOrFail();
        $this->assertSame(1, DocumentExport::query()->count());
        Bus::assertDispatched(GenerateDocumentPdfJob::class, 1);

        (new GenerateDocumentPdfJob($export->id))->handle(app(\App\Services\Documents\DocumentPdfRenderer::class));
        $export->refresh();
        $this->assertSame('completed', $export->status);
        $this->assertTrue(Storage::disk('local')->exists($export->file_path));
        $this->actingAs($other)->get(route('documents.exports.download', $export))->assertForbidden();
        $this->actingAs($teacher)->get(route('documents.exports.download', $export))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $export->update(['expires_at' => now()->subMinute()]);
        $this->artisan('maintenance:cleanup')->assertExitCode(0);
        $this->assertDatabaseMissing('document_exports', ['id' => $export->id]);
        $this->assertFalse(Storage::disk('local')->exists($export->file_path));
    }

    public function test_daily_analytics_aggregation_is_idempotent(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        Document::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $teacher->id,
            'feature_id' => Feature::query()->where('code', 'rpp')->value('id'),
            'title' => 'Dokumen kemarin', 'content_json' => [], 'status' => 'generated',
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);

        $this->artisan('analytics:aggregate-daily')->assertExitCode(0);
        $this->artisan('analytics:aggregate-daily')->assertExitCode(0);

        $this->assertDatabaseHas('daily_analytics', [
            'date' => now()->subDay()->toDateString(), 'documents_count' => 1,
        ]);
        $this->assertSame(1, DB::table('daily_analytics')->count());
    }
}
