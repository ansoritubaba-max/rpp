<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value_json' => 'array', 'is_public' => 'boolean', 'updated_at' => 'datetime'];
    }
}
