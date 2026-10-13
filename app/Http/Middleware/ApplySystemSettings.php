<?php

namespace App\Http\Middleware;

use App\Services\Settings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemSettings
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('system_settings')) {
            $timezone = $this->settings->get('timezone');
            if (is_string($timezone) && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                config()->set('app.timezone', $timezone);
                date_default_timezone_set($timezone);
            }
        }

        return $next($request);
    }
}
