<?php

namespace App\Http\Middleware;

use App\Services\Settings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInMaintenance
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin() && $this->settings->get('maintenance_mode')) {
            abort(503, 'Layanan sedang dalam pemeliharaan. Silakan coba lagi nanti.');
        }

        return $next($request);
    }
}
