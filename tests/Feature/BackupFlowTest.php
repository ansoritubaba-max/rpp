<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Backup\MySqlDumpService;
use App\Services\Backup\BackupRestoreService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use SQLite3;
use Tests\TestCase;
use ZipArchive;

class BackupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_create_verify_and_download_private_backup(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $sourcePath = sys_get_temp_dir().'/ruangajar-test-'.Str::uuid().'.sqlite';
        $source = new SQLite3($sourcePath);
        $source->exec('CREATE TABLE example (id INTEGER PRIMARY KEY, value TEXT)');
        $source->exec("INSERT INTO example (value) VALUES ('tersimpan')");
        $source->close();

        try {
            app()->instance(BackupService::class, new BackupService(app(MySqlDumpService::class), $sourcePath));
            Storage::disk('local')->put('school-logos/1/logo.png', 'logo-example');
            Storage::disk('local')->put('payment-proofs/proof.pdf', 'payment-example');
            Storage::disk('local')->put('branding/logo.png', 'branding-example');
            $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
            $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();

            $this->actingAs($teacher)->get(route('admin.backups.index'))->assertForbidden();
            $this->post(route('admin.backups.store'))->assertForbidden();

            $this->actingAs($admin)->post(route('admin.backups.store'))->assertRedirect(route('admin.backups.index'));
            $backup = Backup::query()->firstOrFail();
            $this->assertSame('completed', $backup->status);
            $this->assertNotNull($backup->checksum_sha256);
            $this->assertTrue(Storage::disk('local')->exists($backup->file_path));
            $this->assertDatabaseHas('audit_logs', ['action' => 'backup.created', 'entity_id' => $backup->id]);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open(Storage::disk('local')->path($backup->file_path)) === true);
            $this->assertNotFalse($zip->locateName('database/database.sqlite'));
            $this->assertSame('logo-example', $zip->getFromName('storage/school-logos/1/logo.png'));
            $this->assertSame('payment-example', $zip->getFromName('storage/payment-proofs/proof.pdf'));
            $this->assertSame('branding-example', $zip->getFromName('storage/branding/logo.png'));
            $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('sqlite', $manifest['database']);
            $databaseBytes = $zip->getFromName('database/database.sqlite');
            $this->assertNotFalse($databaseBytes);
            $zip->close();

            $restoredPath = sys_get_temp_dir().'/ruangajar-restore-test-'.Str::uuid().'.sqlite';
            try {
                file_put_contents($restoredPath, $databaseBytes);
                $restored = new SQLite3($restoredPath, SQLITE3_OPEN_READONLY);
                $this->assertSame('tersimpan', $restored->querySingle('SELECT value FROM example WHERE id = 1'));
                $restored->close();
            } finally {
                if (is_file($restoredPath)) {
                    unlink($restoredPath);
                }
            }

            $this->get(route('admin.backups.index'))->assertOk()->assertSee('Unduh arsip');
            $this->get(route('admin.backups.index'))->assertSee('Pulihkan dari berkas ZIP');
            $this->get(route('admin.backups.restore', $backup))->assertOk()
                ->assertSee('Pemeriksaan arsip berhasil')
                ->assertSee('PULIHKAN-'.$backup->id);
            $this->actingAs($teacher)->get(route('admin.backups.restore', $backup))->assertForbidden();
            $this->actingAs($admin);
            $this->post(route('admin.backups.restore.execute', $backup), ['confirmation' => 'salah', 'acknowledge_loss' => '1'])
                ->assertSessionHasErrors('confirmation');
            $this->post(route('admin.backups.restore.execute', $backup), ['confirmation' => 'PULIHKAN-'.$backup->id])
                ->assertStatus(302)->assertSessionHasErrors('acknowledge_loss');
            $this->assertSame(1, Backup::query()->count());
            $uploaded = UploadedFile::fake()->createWithContent('backup.zip', file_get_contents(Storage::disk('local')->path($backup->file_path)));
            $this->post(route('admin.backups.upload'), ['archive' => $uploaded])
                ->assertRedirect();
            $imported = Backup::query()->where('type', 'uploaded')->firstOrFail();
            $this->get(route('admin.backups.restore', $imported))->assertOk()->assertSee('Pemeriksaan arsip berhasil');
            $this->artisan('backup:restore', ['backup' => $backup->id])->assertSuccessful();
            $this->artisan('backup:restore', ['backup' => $backup->id, '--execute' => true, '--confirm' => 'PULIHKAN-'.$backup->id])->assertFailed();
            $this->get(route('admin.backups.download', $backup))->assertOk()->assertHeader('content-type', 'application/zip');
            $this->assertDatabaseHas('audit_logs', ['action' => 'backup.downloaded', 'entity_id' => $backup->id]);
            $this->actingAs($teacher)->get(route('admin.backups.download', $backup))->assertForbidden();
            Storage::disk('local')->put($backup->file_path, 'tampered');
            $this->actingAs($admin)->get(route('admin.backups.download', $backup))->assertStatus(409);
            $this->get(route('admin.backups.restore', $backup))->assertOk()->assertSee('Backup tidak aman untuk dipulihkan');
            $this->artisan('backup:restore', ['backup' => $backup->id])->assertFailed();
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    public function test_failed_backup_is_recorded_without_a_downloadable_archive(): void
    {
        app()->instance(BackupService::class, new BackupService(app(MySqlDumpService::class), '/path/that/does/not/exist.sqlite'));
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.backups.store'))->assertSessionHasErrors('backup');
        $backup = Backup::query()->firstOrFail();
        $this->assertSame('failed', $backup->status);
        $this->assertNull($backup->file_path);
        $this->get(route('admin.backups.download', $backup))->assertNotFound();
    }

    public function test_upload_rejects_invalid_archives_and_non_admins(): void
    {
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();

        $this->actingAs($teacher)->post(route('admin.backups.upload'), [
            'archive' => UploadedFile::fake()->createWithContent('bad.zip', 'not-a-zip'),
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.backups.upload'), [
            'archive' => UploadedFile::fake()->createWithContent('bad.zip', 'not-a-zip'),
        ])->assertSessionHasErrors('archive');
        $this->assertDatabaseCount('backups', 0);
    }

    public function test_confirmed_web_restore_runs_service_and_returns_to_login(): void
    {
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $backup = Backup::query()->create([
            'type' => 'uploaded',
            'status' => 'completed',
            'file_path' => 'backups/uploads/'.Str::uuid().'.zip',
            'checksum_sha256' => str_repeat('a', 64),
            'started_at' => now(),
        ]);
        $safety = new Backup(['status' => 'completed']);
        $safety->id = 99;
        $mock = \Mockery::mock(BackupRestoreService::class);
        $mock->shouldReceive('restore')->once()->andReturn($safety);
        app()->instance(BackupRestoreService::class, $mock);

        $this->actingAs($admin)->post(route('admin.backups.restore.execute', $backup), [
            'confirmation' => 'PULIHKAN-'.$backup->id,
            'acknowledge_loss' => '1',
        ])->assertRedirect(route('login'))->assertSessionHas('status');
        $this->assertGuest();
    }
}
