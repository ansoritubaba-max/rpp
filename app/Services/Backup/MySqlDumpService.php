<?php

namespace App\Services\Backup;

use RuntimeException;

class MySqlDumpService
{
    public function dump(array $connection, string $destination, string $optionsPath): void
    {
        $database = $this->writeClientOptions($connection, $optionsPath);
        $this->writePrivateFile($destination, '');
        $command = [
            (string) config('backup.mysql_dump_binary', 'mysqldump'),
            '--defaults-extra-file='.$optionsPath,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--hex-blob',
            '--no-tablespaces',
            '--set-gtid-purged=OFF',
            '--default-character-set=utf8mb4',
            $database,
        ];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', $destination, 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Program mysqldump tidak tersedia pada server.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + max(30, (int) config('backup.mysql_dump_timeout_seconds', 300));
        $status = null;
        $errorOutput = '';
        try {
            do {
                $errorOutput = substr($errorOutput.stream_get_contents($pipes[2]), -8192);
                $status = proc_get_status($process);
                if (! $status['running']) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new RuntimeException('Backup MySQL melewati batas waktu.');
                }
                usleep(100000);
            } while (true);
        } finally {
            fclose($pipes[2]);
            proc_close($process);
        }

        if (($status['exitcode'] ?? -1) !== 0 || ! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('Dump MySQL gagal (kode '.($status['exitcode'] ?? -1).'; '.$this->failureCategory($errorOutput).').');
        }
    }

    public function import(array $connection, string $source, string $optionsPath): void
    {
        $database = $this->writeClientOptions($connection, $optionsPath);
        $process = @proc_open([
            (string) config('backup.mysql_client_binary', 'mysql'),
            '--defaults-extra-file='.$optionsPath,
            '--default-character-set=utf8mb4',
            $database,
        ], [
            0 => ['file', $source, 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Program mysql tidak tersedia pada server.');
        }

        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + max(30, (int) config('backup.mysql_restore_timeout_seconds', 1800));
        $status = null;
        $errorOutput = '';
        try {
            do {
                $errorOutput = substr($errorOutput.stream_get_contents($pipes[2]), -8192);
                $status = proc_get_status($process);
                if (! $status['running']) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new RuntimeException('Restore MySQL melewati batas waktu. Data mungkin sudah berubah sebagian; gunakan backup pengaman.');
                }
                usleep(100000);
            } while (true);
        } finally {
            fclose($pipes[2]);
            proc_close($process);
        }

        if (($status['exitcode'] ?? -1) !== 0) {
            throw new RuntimeException('Impor MySQL gagal ('.$this->failureCategory($errorOutput).'). Data mungkin sudah berubah sebagian; gunakan backup pengaman.');
        }
    }

    private function writeClientOptions(array $connection, string $optionsPath): string
    {
        $database = $this->checkedValue($connection['database'] ?? null);
        $username = $this->optionValue($connection['username'] ?? null);
        if ($database === '' || $username === '') {
            throw new RuntimeException('Nama database atau pengguna MySQL belum dikonfigurasi.');
        }

        $options = "[client]\nuser=\"{$username}\"\n";
        $password = $this->optionValue($connection['password'] ?? null);
        if ($password !== '') {
            $options .= "password=\"{$password}\"\n";
        }
        $socket = $this->optionValue($connection['unix_socket'] ?? null);
        if ($socket !== '') {
            $options .= "socket=\"{$socket}\"\n";
        } else {
            $host = $this->optionValue($connection['host'] ?? '127.0.0.1');
            $port = (int) ($connection['port'] ?? 3306);
            if ($host === '' || $port < 1 || $port > 65535) {
                throw new RuntimeException('Host atau port MySQL tidak valid.');
            }
            $options .= "host=\"{$host}\"\nport={$port}\n";
        }

        $this->writePrivateFile($optionsPath, $options);

        return $database;
    }

    private function failureCategory(string $errorOutput): string
    {
        $error = strtolower($errorOutput);

        return match (true) {
            str_contains($error, 'mysql_native_password') && str_contains($error, 'cannot be loaded') => 'plugin mysql_native_password tidak tersedia pada klien mysqldump',
            str_contains($error, 'access denied') => 'akses database ditolak',
            str_contains($error, 'unknown option'), str_contains($error, 'unknown variable') => 'opsi mysqldump tidak didukung',
            str_contains($error, 'authentication') => 'autentikasi database gagal',
            str_contains($error, 'connect') => 'koneksi database gagal',
            str_contains($error, 'permission denied') => 'izin berkas ditolak',
            str_contains($error, 'mysqldump:') => 'kesalahan saat mengekspor database',
            default => 'periksa konfigurasi mysqldump dan database',
        };
    }

    private function optionValue(mixed $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $this->checkedValue($value));
    }

    private function checkedValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (! is_string($value) && ! is_int($value)) {
            throw new RuntimeException('Konfigurasi MySQL tidak valid.');
        }
        $value = (string) $value;
        if (strpbrk($value, "\r\n\0") !== false) {
            throw new RuntimeException('Konfigurasi MySQL mengandung karakter yang tidak valid.');
        }

        return $value;
    }

    private function writePrivateFile(string $path, string $contents): void
    {
        $stream = fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('Berkas sementara backup tidak dapat dibuat.');
        }
        try {
            chmod($path, 0600);
            if ($contents !== '' && fwrite($stream, $contents) !== strlen($contents)) {
                throw new RuntimeException('Berkas sementara backup tidak dapat ditulis.');
            }
        } finally {
            fclose($stream);
        }
    }
}
