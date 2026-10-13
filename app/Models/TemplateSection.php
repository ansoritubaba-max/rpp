<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateSection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_required' => 'boolean', 'is_enabled' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
