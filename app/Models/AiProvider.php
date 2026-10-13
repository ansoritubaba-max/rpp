<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class AiProvider extends Model
{
    protected $guarded = [];

    protected $hidden = ['encrypted_api_key'];

    protected function casts(): array
    {
        return ['timeout_seconds' => 'integer'];
    }

    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: fn () => Crypt::decryptString($this->encrypted_api_key),
            set: fn (string $value) => ['encrypted_api_key' => Crypt::encryptString($value)],
        );
    }

    public function maskedApiKey(): string
    {
        try {
            $key = $this->api_key;

            return $key === '' ? 'Belum diatur' : '••••••••'.substr($key, -4);
        } catch (Throwable) {
            return 'Kunci tidak valid';
        }
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }
}
