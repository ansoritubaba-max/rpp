<?php

namespace App\Http\Middleware;

use App\Services\Backup\RestoreLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockDuringBackupRestore
{
    public function __construct(private readonly RestoreLock $lock) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->lock->active()) {
            return response('Pemulihan data sedang berlangsung. Silakan coba lagi beberapa saat.', 503)
                ->header('Retry-After', '30');
        }

        return $next($request);
    }
}
