<?php

namespace App\Services\Backup;

use App\Jobs\CreateBackupJob;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SQLite3;
use Throwable;
use ZipArchive;

class BackupService
{
    public function __construct(private readonly MySqlDumpService $mySqlDump, private readonly ?string $sourceDatabase = null) {}

    public function enqueue(?User $creator = null): Backup
    {
        $backup = Backup::query()->whereIn('status', ['queued', 'running'])->latest('id')->first();
        if ($backup) {
            return $backup;
        }

        $backup = Backup::query()->create([
            'type' => 'database_and_files',
            'status' => 'queued',
            'started_at' => now(),
            'created_by' => $creator?->id,
        ]);
        CreateBackupJob::dispatch($backup->id)->afterCommit();

        return $backup->fresh();
    }

    public function create(?User $creator = null, ?Backup $queued = null): Backup
    {
        $backup = $queued ?? Backup::query()->create([
            'type' => 'database_and_files',
            'status' => 'running',
            'started_at' => now(),
            'created_by' => $creator?->id,
        ]);
        if ($queued) {
            $backup->update(['status' => 'running', 'started_at' => now()]);
        }
        $disk = Storage::disk('local');
        $token = (string) Str::uuid();
        $driver = DB::connection()->getDriverName();
        $databaseEntry = $driver === 'sqlite' ? 'database/database.sqlite' : 'database/database.sql';
        $temporaryPath = "backups/tmp/{$token}.".($driver === 'sqlite' ? 'sqlite' : 'sql');
        $optionsPath = "backups/tmp/{$token}.cnf";
        $archivePath = 'backups/ruangajar-'.now()->format('Ymd-His')."-{$token}.zip";

        try {
            if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true) || ! extension_loaded('zip')) {
                throw new RuntimeException('Driver database atau ekstensi ZIP belum didukung untuk backup.');
            }

            $disk->makeDirectory('backups/tmp');
            $snapshot = $disk->path($temporaryPath);
            $archive = $disk->path($archivePath);
            if ($driver === 'sqlite') {
                if (! extension_loaded('sqlite3')) {
                    throw new RuntimeException('Ekstensi SQLite3 diperlukan untuk backup.');
                }
                $sourcePath = $this->sourceDatabase ?? DB::connection()->getDatabaseName();
                if (! is_string($sourcePath) || $sourcePath === ':memory:' || ! is_file($sourcePath)) {
                    throw new RuntimeException('Berkas database SQLite tidak ditemukan.');
                }
                $this->snapshotSqlite($sourcePath, $snapshot);
            } else {
                $this->mySqlDump->dump(DB::connection()->getConfig(), $snapshot, $disk->path($optionsPath));
            }
            $this->createArchive($archive, $snapshot, $disk->path(''), $databaseEntry, $driver);

            $backup->update([
                'file_path' => $archivePath,
                'status' => 'completed',
                'size' => filesize($archive),
                'checksum_sha256' => hash_file('sha256', $archive),
                'completed_at' => now(),
            ]);
        } catch (Throwable $error) {
            $disk->delete($archivePath);
            $backup->update([
                'status' => 'failed',
                'error_message' => Str::limit($error->getMessage(), 500),
                'completed_at' => now(),
            ]);
            Log::error('Backup creation failed', ['backup_id' => $backup->id, 'exception' => $error]);
        } finally {
            $disk->delete($temporaryPath);
            $disk->delete($optionsPath);
        }

        return $backup->fresh();
    }

    private function snapshotSqlite(string $sourcePath, string $snapshotPath): void
    {
        $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
        $destination = new SQLite3($snapshotPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        try {
            $source->busyTimeout(5000);
            if (! $source->backup($destination)) {
                throw new RuntimeException('Snapshot database tidak berhasil dibuat.');
            }
        } finally {
            $destination->close();
            $source->close();
        }

        $check = new SQLite3($snapshotPath, SQLITE3_OPEN_READONLY);
        try {
            if ($check->querySingle('PRAGMA integrity_check') !== 'ok') {
                throw new RuntimeException('Integritas snapshot database tidak valid.');
            }
        } finally {
            $check->close();
        }
    }

    private function createArchive(string $archivePath, string $snapshotPath, string $privateRoot, string $databaseEntry, string $driver): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Arsip backup tidak dapat dibuat.');
        }

        $included = [$databaseEntry];
        $fileHashes = [$databaseEntry => hash_file('sha256', $snapshotPath)];
        try {
            if (! $zip->addFile($snapshotPath, $databaseEntry)) {
                throw new RuntimeException('Snapshot database tidak dapat ditambahkan ke arsip.');
            }

            $root = realpath($privateRoot);
            foreach (['school-logos', 'payment-proofs', 'branding'] as $directory) {
                foreach (Storage::disk('local')->allFiles($directory) as $path) {
                    $absolute = realpath(Storage::disk('local')->path($path));
                    if ($absolute === false || ! str_starts_with($absolute, $root.DIRECTORY_SEPARATOR) || ! is_file($absolute)) {
                        throw new RuntimeException('Berkas privat tidak aman untuk dimasukkan ke backup.');
                    }
                    if (! $zip->addFile($absolute, 'storage/'.$path)) {
                        throw new RuntimeException('Berkas privat tidak dapat ditambahkan ke arsip.');
                    }
                    $included[] = 'storage/'.$path;
                    $fileHashes['storage/'.$path] = hash_file('sha256', $absolute);
                }
            }

            $manifest = [
                'app' => 'RuangAjar',
                'created_at' => now()->toIso8601String(),
                'database' => $driver,
                'files' => $included,
                'database_sha256' => $fileHashes[$databaseEntry],
                'file_sha256' => $fileHashes,
            ];
            $manifest['signature'] = hash_hmac('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), (string) config('app.key'));
            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Manifest backup tidak dapat ditambahkan ke arsip.');
            }
        } finally {
            $zip->close();
        }

        $check = new ZipArchive;
        if ($check->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Arsip backup tidak dapat diverifikasi.');
        }
        try {
            if ($check->locateName($databaseEntry) === false || $check->locateName('manifest.json') === false) {
                throw new RuntimeException('Isi arsip backup tidak lengkap.');
            }
        } finally {
            $check->close();
        }

        chmod($archivePath, 0600);
    }
}
