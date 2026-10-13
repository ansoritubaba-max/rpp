<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelHealth extends Model
{
    protected $table = 'ai_model_health';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'failure_count' => 'integer',
            'cooldown_until' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
