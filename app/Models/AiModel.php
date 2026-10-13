<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiModel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'max_output_tokens' => 'integer',
            'timeout_seconds' => 'integer',
            'estimated_input_cost' => 'decimal:8',
            'estimated_output_cost' => 'decimal:8',
            'internal_daily_limit' => 'integer',
            'cooldown_seconds' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function health(): HasOne
    {
        return $this->hasOne(AiModelHealth::class, 'ai_model_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(AiRoute::class, 'ai_model_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'ai_routes', 'ai_model_id', 'feature_id')
            ->withPivot(['priority', 'is_active'])
            ->withTimestamps();
    }
}
