<?php

namespace App\Services\Monitoring;

use App\Models\OperationalEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalEventRecorder
{
    public function record(string $type, ?int $userId = null, ?int $documentId = null, ?string $ipAddress = null): void
    {
        try {
            OperationalEvent::query()->create([
                'type' => $type,
                'user_id' => $userId,
                'document_id' => $documentId,
                'ip_fingerprint' => $ipAddress ? hash_hmac('sha256', $ipAddress, (string) config('app.key')) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $error) {
            Log::error('Operational event recording failed', ['type' => $type, 'exception' => $error]);
        }
    }
}
