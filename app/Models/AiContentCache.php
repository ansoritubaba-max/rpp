<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiContentCache extends Model
{
    protected $table = 'ai_content_cache';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_signature_json' => 'array',
            'output_json' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
