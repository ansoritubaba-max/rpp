<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;

class SessionDeviceLabel
{
    public function fromUserAgent(?string $userAgent): string
    {
        $agent = Str::lower($userAgent ?? '');
        $browser = match (true) {
            Str::contains($agent, 'edg/') => 'Edge',
            Str::contains($agent, 'chrome/') => 'Chrome',
            Str::contains($agent, 'firefox/') => 'Firefox',
            Str::contains($agent, 'safari/') => 'Safari',
            default => 'Browser tidak dikenal',
        };
        $device = match (true) {
            Str::contains($agent, ['iphone', 'ipad', 'android', 'mobile']) => 'Ponsel / tablet',
            Str::contains($agent, ['windows', 'macintosh', 'linux']) => 'Komputer',
            default => 'Perangkat tidak dikenal',
        };

        return "{$browser} · {$device}";
    }
}
