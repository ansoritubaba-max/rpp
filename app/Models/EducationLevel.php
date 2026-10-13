<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationLevel extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class)->orderBy('sort_order');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class)->orderBy('name');
    }

    public function vocationalPrograms(): HasMany
    {
        return $this->hasMany(VocationalProgram::class)->orderBy('sort_order');
    }
}
