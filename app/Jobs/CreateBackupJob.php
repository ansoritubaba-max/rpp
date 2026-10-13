<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\Audit\AuditService;
use App\Services\Backup\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $backupId)
    {
        $this->onQueue('maintenance');
    }

    public function handle(BackupService $service, AuditService $audit): void
    {
        $backup = Backup::query()->findOrFail($this->backupId);
        if ($backup->status !== 'queued') {
            return;
        }

        $result = $service->create($backup->creator, $backup);
        $audit->record('backup.created', $result, [], ['status' => $result->status, 'size' => $result->size]);
    }

    public function failed(Throwable $error): void
    {
        Backup::query()->whereKey($this->backupId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed',
            'error_message' => Str::limit($error->getMessage(), 500),
            'completed_at' => now(),
        ]);
    }
}
