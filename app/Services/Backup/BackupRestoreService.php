<?php

namespace App\Services\Backup;

use App\Models\Backup;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use SQLite3;
use ZipArchive;

class BackupRestoreService
{
    public function __construct(
        private readonly BackupArchiveInspector $inspector,
        private readonly BackupService $backups,
        private readonly MySqlDumpService $mySql,
    ) {}

    public function restore(Backup $backup, ?Closure $onSafety = null): Backup
    {
        $manifest = $this->inspector->inspect($backup);
        $driver = DB::connection()->getDriverName();
        if ($driver !== $manifest['driver'] && ! (in_array($driver, ['mysql', 'mariadb'], true) && in_array($manifest['driver'], ['mysql', 'mariadb'], true))) {
            throw new RuntimeException('Jenis database arsip berbeda dari database aktif. Restore dibatalkan.');
        }

        $safety = $this->backups->create();
        if ($safety->status !== 'completed') {
            throw new RuntimeException('Backup pengaman sebelum restore gagal. Restore dibatalkan.');
        }
        $onSafety?->__invoke($safety);

        $disk = Storage::disk('local');
        $temporary = $disk->path('backups/tmp/restore-'.Str::uuid());
        File::ensureDirectoryExists($temporary, 0700);
        try {
            $zip = new ZipArchive;
            if ($zip->open($manifest['archive_path']) !== true) {
                throw new RuntimeException('Arsip tidak dapat dibuka setelah verifikasi.');
            }
            try {
                foreach ($manifest['files'] as $entry) {
                    $source = $zip->getStream($entry);
                    if (! is_resource($source)) {
                        throw new RuntimeException('Berkas di dalam backup tidak dapat dibaca.');
                    }
                    $destination = $temporary.DIRECTORY_SEPARATOR.$entry;
                    File::ensureDirectoryExists(dirname($destination), 0700);
                    $target = fopen($destination, 'xb');
                    if ($target === false) {
                        fclose($source);
                        throw new RuntimeException('Ruang sementara restore tidak dapat ditulis.');
                    }
                    try {
                        $copied = stream_copy_to_stream($source, $target);
                        $expected = $zip->statName($entry)['size'] ?? null;
                        if ($copied === false || $copied !== $expected) {
                            throw new RuntimeException('Arsip tidak dapat diekstrak secara lengkap.');
                        }
                    } finally {
                        fclose($target);
                        fclose($source);
                    }
                }
            } finally {
                $zip->close();
            }
            if ($driver === 'sqlite') {
                $this->restoreSqlite($temporary.'/database/database.sqlite');
            } else {
                $sqlPath = $temporary.'/database/database.sql';
                $this->assertRestorableMySqlDump($sqlPath);
                $optionsPath = $temporary.'/mysql.cnf';
                try {
                    $this->clearMySqlSchema();
                    $this->mySql->import(DB::connection()->getConfig(), $sqlPath, $optionsPath);
                } finally {
                    File::delete($optionsPath);
                }
            }
            DB::purge();
            DB::reconnect();
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new RuntimeException('Pembaruan struktur database setelah restore gagal; backup pengaman masih tersedia.');
            }
            $this->restoreFiles($temporary);
            DB::reconnect();

            Backup::query()->updateOrCreate(['file_path' => $backup->file_path], [
                'type' => $backup->type,
                'status' => 'completed',
                'size' => $backup->size,
                'checksum_sha256' => $backup->checksum_sha256,
                'started_at' => $backup->started_at,
                'completed_at' => $backup->completed_at,
            ]);

            return Backup::query()->updateOrCreate(['file_path' => $safety->file_path], [
                'type' => $safety->type,
                'status' => 'completed',
                'file_path' => $safety->file_path,
                'size' => $safety->size,
                'checksum_sha256' => $safety->checksum_sha256,
                'started_at' => $safety->started_at,
                'completed_at' => $safety->completed_at,
            ]);
        } finally {
            File::deleteDirectory($temporary);
        }
    }

    private function restoreSqlite(string $snapshot): void
    {
        $check = new SQLite3($snapshot, SQLITE3_OPEN_READONLY);
        try {
            if ($check->querySingle('PRAGMA integrity_check') !== 'ok') {
                throw new RuntimeException('Snapshot SQLite tidak valid.');
            }
        } finally {
            $check->close();
        }
        $database = DB::connection()->getDatabaseName();
        if (! is_string($database) || $database === ':memory:' || ! is_file($database)) {
            throw new RuntimeException('Lokasi database SQLite aktif tidak valid.');
        }
        DB::disconnect();
        if (! rename($snapshot, $database)) {
            throw new RuntimeException('Database SQLite gagal diganti; backup pengaman masih tersedia.');
        }
    }

    private function assertRestorableMySqlDump(string $path): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Snapshot MySQL tidak dapat dibaca. Restore dibatalkan.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^\s*SET\s+@@GLOBAL\.GTID_PURGED\b/i', $line)) {
                    throw new RuntimeException('Arsip MySQL ini masih memuat GTID server. Buat backup baru sebelum restore agar database tidak rusak.');
                }
            }
            if (! feof($handle)) {
                throw new RuntimeException('Snapshot MySQL tidak dapat diperiksa. Restore dibatalkan.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function clearMySqlSchema(): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        if (! is_string($database) || $database === '') {
            throw new RuntimeException('Database tujuan restore MySQL tidak valid.');
        }

        $prefix = $this->quoteMySqlIdentifier($database).'.';
        $events = $connection->table('information_schema.EVENTS')
            ->where('EVENT_SCHEMA', $database)->get(['EVENT_NAME as name']);
        foreach ($events as $event) {
            $connection->statement('DROP EVENT IF EXISTS '.$prefix.$this->quoteMySqlIdentifier($event->name));
        }

        $routines = $connection->table('information_schema.ROUTINES')
            ->where('ROUTINE_SCHEMA', $database)->get(['ROUTINE_NAME as name', 'ROUTINE_TYPE as type']);
        foreach ($routines as $routine) {
            if (! in_array($routine->type, ['PROCEDURE', 'FUNCTION'], true)) {
                throw new RuntimeException('Jenis rutin MySQL tidak dikenali. Restore dibatalkan.');
            }
            $connection->statement('DROP '.$routine->type.' IF EXISTS '.$prefix.$this->quoteMySqlIdentifier($routine->name));
        }

        $schema = $connection->getSchemaBuilder();
        $schema->dropAllViews();
        $schema->dropAllTables();
    }

    private function quoteMySqlIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private function restoreFiles(string $temporary): void
    {
        $root = Storage::disk('local')->path('');
        $swapped = [];
        try {
            foreach (['school-logos', 'payment-proofs', 'branding'] as $directory) {
                $current = $root.DIRECTORY_SEPARATOR.$directory;
                $staged = $temporary.'/storage/'.$directory;
                $previous = $temporary.'/previous-'.$directory;
                File::ensureDirectoryExists($staged, 0700);
                $hadCurrent = is_dir($current);
                if ($hadCurrent && ! rename($current, $previous)) {
                    throw new RuntimeException('Berkas lama tidak dapat dipindahkan.');
                }
                if (! rename($staged, $current)) {
                    if ($hadCurrent) {
                        rename($previous, $current);
                    }
                    throw new RuntimeException('Berkas backup tidak dapat dipulihkan.');
                }
                $swapped[] = [$current, $previous, $hadCurrent];
            }
        } catch (\Throwable $error) {
            foreach (array_reverse($swapped) as [$current, $previous, $hadCurrent]) {
                File::deleteDirectory($current);
                if ($hadCurrent) {
                    rename($previous, $current);
                }
            }
            throw new RuntimeException($error->getMessage().' Backup pengaman masih tersedia.', previous: $error);
        }
    }
}
