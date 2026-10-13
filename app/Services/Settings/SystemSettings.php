<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;

class SystemSettings
{
    private const DEFAULTS = [
        'app_name' => 'RuangAjar',
        'logo_path' => null,
        'favicon_path' => null,
        'support_email' => null,
        'timezone' => 'Asia/Jakarta',
        'paper_size' => 'a4',
        'margin_top' => 20,
        'margin_right' => 18,
        'margin_bottom' => 20,
        'margin_left' => 18,
        'session_limit' => 3,
        'trial_days' => 7,
        'trial_plan_id' => null,
        'ai_usd_idr_rate' => null,
        'maintenance_mode' => false,
        'generator_user_per_minute' => 8,
        'generator_feature_per_minute' => 4,
        'generator_ip_per_minute' => 60,
        'generator_endpoint_per_minute' => 40,
        'generator_burst_per_10_seconds' => 12,
        'generator_failure_threshold' => 6,
        'generator_distinct_ip_threshold' => 5,
        'generator_block_minutes' => 10,
    ];

    public function all(): array
    {
        $values = self::DEFAULTS;
        if (! Schema::hasTable('system_settings')) {
            return $values;
        }

        foreach (SystemSetting::query()->get(['key', 'value_json']) as $setting) {
            if (array_key_exists($setting->key, $values)) {
                $values[$setting->key] = $setting->value_json['value'] ?? null;
            }
        }

        return $values;
    }

    public function get(string $key): mixed
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            return null;
        }

        if (! Schema::hasTable('system_settings')) {
            return self::DEFAULTS[$key];
        }

        $setting = SystemSetting::query()->where('key', $key)->first();

        return $setting ? ($setting->value_json['value'] ?? null) : self::DEFAULTS[$key];
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(['key' => $key], [
                'value_json' => ['value' => $value],
                'group' => in_array($key, ['logo_path', 'favicon_path', 'app_name', 'support_email'], true) ? 'branding' : (str_starts_with($key, 'trial_') || $key === 'ai_usd_idr_rate' ? 'billing' : 'operations'),
                'is_public' => in_array($key, ['logo_path', 'favicon_path', 'app_name', 'support_email'], true),
                'updated_at' => now(),
            ]);
        }
    }

    public function logoUrl(): string
    {
        return $this->get('logo_path') ? route('branding.show', 'logo') : asset('rg.png');
    }

    public function faviconUrl(): string
    {
        return $this->get('favicon_path') ? route('branding.show', 'favicon') : asset('rg.png');
    }
}
