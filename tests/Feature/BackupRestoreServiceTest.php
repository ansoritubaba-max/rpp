<?php

namespace Tests\Feature;

use App\Services\Backup\BackupRestoreService;
use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupRestoreServiceTest extends TestCase
{
    public function test_sqlite_restore_replaces_database_and_private_files_on_isolated_database(): void
    {
        $databasePath = sys_get_temp_dir().'/ruangajar-restore-isolated-'.Str::uuid().'.sqlite';
        touch($databasePath);
        $originalDefault = config('database.default');
        $connection = config('database.connections.sqlite');
        $connection['database'] = $databasePath;
        config()->set('database.connections.restore_isolated', $connection);
        config()->set('database.default', 'restore_isolated');
        Storage::fake('local');

        try {
            DB::purge('restore_isolated');
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            DB::statement('CREATE TABLE restore_marker (value TEXT NOT NULL)');
            DB::table('restore_marker')->insert(['value' => 'sebelum']);
            Storage::disk('local')->put('branding/logo.png', 'logo-sebelum');

            $backup = app(BackupService::class)->create();
            $this->assertSame('completed', $backup->status);
            DB::table('restore_marker')->update(['value' => 'sesudah']);
            Storage::disk('local')->put('branding/logo.png', 'logo-sesudah');

            $safety = app(BackupRestoreService::class)->restore($backup);

            $this->assertSame('sebelum', DB::table('restore_marker')->value('value'));
            $this->assertSame('logo-sebelum', Storage::disk('local')->get('branding/logo.png'));
            $this->assertSame('completed', $safety->status);
            $this->assertTrue(Storage::disk('local')->exists($safety->file_path));
        } finally {
            DB::disconnect('restore_isolated');
            DB::purge('restore_isolated');
            config()->set('database.default', $originalDefault);
            config()->offsetUnset('database.connections.restore_isolated');
            if (is_file($databasePath)) {
                unlink($databasePath);
            }
        }
    }
}
