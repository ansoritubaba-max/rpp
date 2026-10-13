<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->email_verified_at === null) {
            return $request->expectsJson()
                ? abort(403, 'Alamat email belum diverifikasi.')
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
