<?php

namespace App\Services\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BackupArchiveInspector
{
    public function inspect(Backup $backup): array
    {
        if ($backup->status !== 'completed' || ! is_string($backup->file_path)
            || ! preg_match('/^backups\/(?:ruangajar-\d{8}-\d{6}-[0-9a-f-]{36}|uploads\/[0-9a-f-]{36})\.zip$/', $backup->file_path)) {
            throw new RuntimeException('Backup ini belum siap dipulihkan.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($backup->file_path)) {
            throw new RuntimeException('Berkas backup tidak ditemukan di server.');
        }
        $archivePath = $disk->path($backup->file_path);
        if (! is_string($backup->checksum_sha256)
            || ! hash_equals($backup->checksum_sha256, hash_file('sha256', $archivePath))) {
            throw new RuntimeException('Integritas arsip backup tidak valid. Jangan pulihkan arsip ini.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Arsip ZIP tidak dapat dibuka.');
        }

        try {
            if ($zip->numFiles > 20000) {
                throw new RuntimeException('Arsip berisi terlalu banyak berkas.');
            }
            $rawManifest = $zip->getFromName('manifest.json');
            $manifest = is_string($rawManifest) ? json_decode($rawManifest, true) : null;
            $driver = $manifest['database'] ?? null;
            $files = $manifest['files'] ?? null;
            $databaseEntry = $driver === 'sqlite' ? 'database/database.sqlite' : 'database/database.sql';
            if (! is_array($manifest) || ($manifest['app'] ?? null) !== 'RuangAjar'
                || ! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)
                || ! is_array($files) || ! in_array($databaseEntry, $files, true)
                || ! is_string($manifest['database_sha256'] ?? null)
                || ! preg_match('/^[0-9a-f]{64}$/', $manifest['database_sha256'])) {
                throw new RuntimeException('Manifest backup tidak lengkap atau tidak dikenali.');
            }

            $authenticated = false;
            if (isset($manifest['signature'])) {
                $signature = $manifest['signature'];
                unset($manifest['signature']);
                if (! is_string($signature) || ! preg_match('/^[0-9a-f]{64}$/', $signature)
                    || ! hash_equals(hash_hmac('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), (string) config('app.key')), $signature)) {
                    throw new RuntimeException('Tanda tangan backup tidak valid. Pastikan APP_KEY sesuai dengan saat backup dibuat.');
                }
                $authenticated = true;
            }

            $listed = [];
            foreach ($files as $entry) {
                if (! is_string($entry) || isset($listed[$entry]) || ! $this->allowedEntry($entry, $databaseEntry)
                    || $zip->locateName($entry) === false) {
                    throw new RuntimeException('Daftar berkas backup tidak aman atau tidak lengkap.');
                }
                $listed[$entry] = true;
            }
            if ($zip->numFiles !== count($listed) + 1) {
                throw new RuntimeException('Arsip berisi berkas yang tidak tercantum di manifest.');
            }
            $totalSize = 0;
            $maximumSize = max(1, (int) config('backup.max_uncompressed_bytes', 2147483648));
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if ($name !== 'manifest.json' && ! isset($listed[$name])) {
                    throw new RuntimeException('Arsip berisi berkas yang tidak dikenal.');
                }
                $size = $zip->statIndex($index)['size'] ?? null;
                if (! is_int($size) || $size < 0 || $size > $maximumSize - $totalSize) {
                    throw new RuntimeException('Ukuran isi arsip melebihi batas aman server.');
                }
                $totalSize += $size;
            }

            if ($authenticated) {
                $fileHashes = $manifest['file_sha256'] ?? null;
                if (! is_array($fileHashes) || count($fileHashes) !== count($listed)) {
                    throw new RuntimeException('Daftar checksum berkas backup tidak lengkap.');
                }
                foreach ($files as $entry) {
                    if (! is_string($fileHashes[$entry] ?? null) || ! preg_match('/^[0-9a-f]{64}$/', $fileHashes[$entry])) {
                        throw new RuntimeException('Checksum berkas backup tidak valid.');
                    }
                    $entryStream = $zip->getStream($entry);
                    if (! is_resource($entryStream)) {
                        throw new RuntimeException('Berkas backup tidak dapat dibaca.');
                    }
                    try {
                        $entryHash = hash_init('sha256');
                        hash_update_stream($entryHash, $entryStream);
                        if (! hash_equals($fileHashes[$entry], hash_final($entryHash))) {
                            throw new RuntimeException('Isi berkas backup tidak cocok dengan tanda tangan.');
                        }
                    } finally {
                        fclose($entryStream);
                    }
                }
            }

            $stream = $zip->getStream($databaseEntry);
            if (! is_resource($stream)) {
                throw new RuntimeException('Snapshot database tidak dapat dibaca.');
            }
            try {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                if (! hash_equals($manifest['database_sha256'], hash_final($hash))) {
                    throw new RuntimeException('Checksum snapshot database tidak cocok.');
                }
            } finally {
                fclose($stream);
            }

            return [
                'driver' => $driver,
                'created_at' => $manifest['created_at'] ?? null,
                'database_entry' => $databaseEntry,
                'files' => $files,
                'storage_files' => count($files) - 1,
                'authenticated' => $authenticated,
                'archive_path' => $archivePath,
            ];
        } finally {
            $zip->close();
        }
    }

    private function allowedEntry(string $entry, string $databaseEntry): bool
    {
        if ($entry === $databaseEntry) {
            return true;
        }

        return (bool) preg_match('#^storage/(school-logos|payment-proofs|branding)/(?!\.)(?:[A-Za-z0-9._-]+/)*[A-Za-z0-9._-]+$#D', $entry)
            && ! str_contains($entry, '..');
    }
}
