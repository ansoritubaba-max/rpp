<?php

namespace App\Services\Maintenance;

use App\Models\AiContentCache;
use App\Models\AiModelHealth;
use App\Models\DocumentExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackgroundCleanupService
{
    public function run(): array
    {
        $otp = DB::table('otp_codes')->where('expires_at', '<=', now())->delete();
        $cache = AiContentCache::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();
        $health = AiModelHealth::query()->where('status', 'cooldown')->where('cooldown_until', '<=', now())
            ->update(['status' => 'unknown', 'cooldown_until' => null, 'updated_at' => now()]);

        $disk = Storage::disk('local');
        $files = 0;
        foreach ($disk->allFiles('backups/tmp') as $path) {
            if (! preg_match('#^backups/tmp/[0-9a-f-]{36}\.(?:sql|sqlite|cnf)$#i', $path)) {
                continue;
            }
            if ($disk->lastModified($path) > now()->subDay()->timestamp) {
                continue;
            }
            $files += (int) $disk->delete($path);
        }

        DocumentExport::query()->where('status', 'completed')->where('expires_at', '<=', now())
            ->chunkById(100, function ($exports) use ($disk, &$files): void {
                foreach ($exports as $export) {
                    if (is_string($export->file_path) && preg_match('#^document-exports/\d+-[0-9a-f-]{36}\.pdf$#', $export->file_path)) {
                        $files += (int) $disk->delete($export->file_path);
                    }
                    $export->delete();
                }
            });

        return compact('otp', 'cache', 'health', 'files');
    }
}
