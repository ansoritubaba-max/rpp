<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(string $action, Model $entity, array $before = [], array $after = []): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'before_json' => $this->sanitize($before),
            'after_json' => $this->sanitize($after),
            'ip_address' => request()->ip(),
            'user_agent' => str(request()->userAgent())->limit(1000)->toString(),
        ]);
    }

    private function sanitize(array $values): array
    {
        return collect($values)
            ->except(['password', 'remember_token', 'encrypted_api_key', 'api_key'])
            ->map(fn ($value) => is_string($value) ? str($value)->limit(2000)->toString() : $value)
            ->all();
    }
}
