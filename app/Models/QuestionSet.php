<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QuestionSet extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata_json' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_set_items')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
