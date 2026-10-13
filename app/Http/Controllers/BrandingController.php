<?php

namespace App\Http\Controllers;

use App\Services\Settings\SystemSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandingController extends Controller
{
    public function __invoke(string $kind, SystemSettings $settings): Response
    {
        abort_unless(in_array($kind, ['logo', 'favicon'], true), 404);
        $path = $settings->get($kind.'_path');
        abort_unless(is_string($path) && Str::startsWith($path, 'branding/') && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
