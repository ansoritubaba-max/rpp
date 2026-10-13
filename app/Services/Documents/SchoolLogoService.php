<?php

namespace App\Services\Documents;

use App\Models\School;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SchoolLogoService
{
    public function path(School $school, string $side): ?string
    {
        if (! in_array($side, ['left', 'right'], true)) {
            return null;
        }

        $path = $school->{'logo_'.$side};
        if (! is_string($path) || ! Str::startsWith($path, 'school-logos/'.$school->user_id.'/')) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    public function mimeType(string $path): ?string
    {
        $mimeType = Storage::disk('local')->mimeType($path);

        return in_array($mimeType, ['image/png', 'image/jpeg'], true) ? $mimeType : null;
    }

    public function dataUri(School $school, string $side): ?string
    {
        $path = $this->path($school, $side);
        $mimeType = $path ? $this->mimeType($path) : null;

        return $mimeType ? 'data:'.$mimeType.';base64,'.base64_encode(Storage::disk('local')->get($path)) : null;
    }
}
