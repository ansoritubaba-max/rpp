<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot(['is_enabled', 'generation_limit', 'regeneration_limit']);
    }

    public function aiModels(): BelongsToMany
    {
        return $this->belongsToMany(AiModel::class, 'ai_routes', 'feature_id', 'ai_model_id')
            ->withPivot(['priority', 'is_active'])
            ->withTimestamps();
    }
}
