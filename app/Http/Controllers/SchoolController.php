<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolRequest;
use App\Services\Documents\SchoolLogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SchoolController extends Controller
{
    public function edit(): View
    {
        return view('schools.edit', [
            'school' => request()->user()->schools()->first(),
        ]);
    }

    public function update(SchoolRequest $request): RedirectResponse
    {
        $school = $request->user()->schools()->first();
        $values = $request->safe()->except(['logo_left', 'logo_right', 'remove_logo_left', 'remove_logo_right']);
        $newPaths = [];
        $oldPaths = [];

        try {
            foreach (['left', 'right'] as $side) {
                $field = 'logo_'.$side;
                if ($request->hasFile($field)) {
                    $oldPaths[] = $school?->{$field};
                    $file = $request->file($field);
                    $path = $file->storeAs('school-logos/'.$request->user()->id, Str::uuid().'.'.$file->extension(), 'local');
                    if (! $path) {
                        throw new RuntimeException('Logo sekolah gagal disimpan.');
                    }
                    $values[$field] = $path;
                    $newPaths[] = $path;
                } elseif ($request->boolean('remove_'.$field)) {
                    $oldPaths[] = $school?->{$field};
                    $values[$field] = null;
                }
            }

            DB::transaction(fn () => $request->user()->schools()->updateOrCreate(
                ['id' => $school?->id],
                $values,
            ));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);

            throw $exception;
        }

        $safeOldPaths = collect($oldPaths)
            ->filter(fn ($path) => is_string($path) && Str::startsWith($path, 'school-logos/'.$request->user()->id.'/'))
            ->all();
        Storage::disk('local')->delete($safeOldPaths);

        return back()->with('success', 'Profil sekolah berhasil disimpan.');
    }

    public function logo(Request $request, string $side, SchoolLogoService $logos): Response
    {
        $school = $request->user()->schools()->firstOrFail();
        $path = $logos->path($school, $side);
        $mimeType = $path ? $logos->mimeType($path) : null;
        abort_unless($mimeType, 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
