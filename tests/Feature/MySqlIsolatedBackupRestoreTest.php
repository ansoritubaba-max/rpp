<?php

namespace Tests\Feature;

use App\Services\Backup\BackupRestoreService;
use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MySqlIsolatedBackupRestoreTest extends TestCase
{
    public function test_mysql_restore_replaces_only_an_isolated_database_and_private_files(): void
    {
        $socket = getenv('RUANGAJAR_MYSQL_RESTORE_TEST_SOCKET');
        if (! is_string($socket) || $socket === '') {
            $this->markTestSkipped('Set RUANGAJAR_MYSQL_RESTORE_TEST_SOCKET untuk uji restore MySQL terisolasi.');
        }

        $database = 'ruangajar_restore_test_'.strtolower(Str::random(12));
        $originalDefault = config('database.default');
        $originalDumpBinary = config('backup.mysql_dump_binary');
        $originalClientBinary = config('backup.mysql_client_binary');
        $clientDirectory = getenv('RUANGAJAR_MYSQL_RESTORE_TEST_CLIENT_DIR');
        if (is_string($clientDirectory) && $clientDirectory !== '') {
            config()->set('backup.mysql_dump_binary', $clientDirectory.'/mysqldump');
            config()->set('backup.mysql_client_binary', $clientDirectory.'/mysql');
        }
        $applicationConnection = config('database.connections.mysql');
        $applicationUser = $applicationConnection['username'] ?? null;
        if (! is_string($applicationUser) || $applicationUser === '') {
            $this->markTestSkipped('Pengguna MySQL aplikasi belum dikonfigurasi.');
        }
        $adminConnection = array_replace(config('database.connections.mysql'), [
            'url' => null,
            'host' => 'localhost',
            'database' => 'mysql',
            'username' => 'root',
            'password' => getenv('RUANGAJAR_MYSQL_RESTORE_TEST_ADMIN_PASSWORD') ?: '',
            'unix_socket' => $socket,
        ]);
        config()->set('database.connections.restore_admin', $adminConnection);
        $admin = DB::connection('restore_admin');
        $account = $admin->getPdo()->quote($applicationUser)."@'localhost'";
        $created = false;
        $granted = false;

        try {
            $admin->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $created = true;
            $admin->statement("GRANT ALL PRIVILEGES ON `{$database}`.* TO {$account}");
            $granted = true;

            config()->set('database.connections.restore_mysql', array_replace($applicationConnection, [
                'url' => null,
                'database' => $database,
            ]));
            config()->set('database.default', 'restore_mysql');
            DB::purge('restore_mysql');
            Storage::fake('local');

            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            DB::statement('CREATE TABLE restore_marker (value VARCHAR(20) NOT NULL)');
            DB::table('restore_marker')->insert(['value' => 'sebelum']);
            Storage::disk('local')->put('branding/logo.png', 'logo-sebelum');

            $backup = app(BackupService::class)->create();
            $this->assertSame('completed', $backup->status, (string) $backup->error_message);
            DB::table('restore_marker')->update(['value' => 'sesudah']);
            DB::statement('CREATE TABLE post_backup_table (id INT NOT NULL)');
            DB::statement('CREATE VIEW post_backup_view AS SELECT value FROM restore_marker');
            DB::statement('CREATE PROCEDURE post_backup_procedure() SELECT 1');
            DB::statement('CREATE EVENT post_backup_event ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY DO SELECT 1');
            Storage::disk('local')->put('branding/logo.png', 'logo-sesudah');
            Storage::disk('local')->put('branding/baru.png', 'berkas-sesudah');

            $safety = app(BackupRestoreService::class)->restore($backup);

            $this->assertSame('sebelum', DB::table('restore_marker')->value('value'));
            $this->assertFalse(DB::getSchemaBuilder()->hasTable('post_backup_table'));
            $this->assertFalse(DB::getSchemaBuilder()->hasView('post_backup_view'));
            $this->assertSame(0, DB::table('information_schema.ROUTINES')->where('ROUTINE_SCHEMA', $database)->where('ROUTINE_NAME', 'post_backup_procedure')->count());
            $this->assertSame(0, DB::table('information_schema.EVENTS')->where('EVENT_SCHEMA', $database)->where('EVENT_NAME', 'post_backup_event')->count());
            $this->assertSame('logo-sebelum', Storage::disk('local')->get('branding/logo.png'));
            $this->assertFalse(Storage::disk('local')->exists('branding/baru.png'));
            $this->assertSame('completed', $safety->status);
            $this->assertTrue(Storage::disk('local')->exists($safety->file_path));
        } finally {
            DB::disconnect('restore_mysql');
            DB::purge('restore_mysql');
            config()->set('database.default', $originalDefault);
            config()->set('backup.mysql_dump_binary', $originalDumpBinary);
            config()->set('backup.mysql_client_binary', $originalClientBinary);
            config()->offsetUnset('database.connections.restore_mysql');
            if ($granted) {
                $admin->statement("REVOKE ALL PRIVILEGES ON `{$database}`.* FROM {$account}");
            }
            if ($created) {
                $admin->statement("DROP DATABASE `{$database}`");
            }
            DB::disconnect('restore_admin');
            DB::purge('restore_admin');
            config()->offsetUnset('database.connections.restore_admin');
        }
    }
}
