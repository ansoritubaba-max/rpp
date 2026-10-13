<?php

namespace App\Services\Backup;

use Closure;
use RuntimeException;

class RestoreLock
{
    public function run(Closure $callback): mixed
    {
        $handle = fopen(storage_path('framework/backup-restore.lock'), 'c');
        if ($handle === false) {
            throw new RuntimeException('Kunci pemulihan tidak dapat dibuat pada server.');
        }
        chmod(storage_path('framework/backup-restore.lock'), 0600);
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Pemulihan lain sedang berjalan. Tunggu sampai selesai.');
        }
        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function active(): bool
    {
        $handle = fopen(storage_path('framework/backup-restore.lock'), 'c');
        if ($handle === false) {
            return false;
        }
        $available = flock($handle, LOCK_SH | LOCK_NB);
        if ($available) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return ! $available;
    }
}
