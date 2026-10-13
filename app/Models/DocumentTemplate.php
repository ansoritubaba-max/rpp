<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'margin_top' => 'decimal:2',
            'margin_right' => 'decimal:2',
            'margin_bottom' => 'decimal:2',
            'margin_left' => 'decimal:2',
            'font_size' => 'decimal:1',
            'line_height' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TemplateSection::class, 'template_id')->orderBy('sort_order');
    }
}
