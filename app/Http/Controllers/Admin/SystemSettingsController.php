<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Models\Plan;
use App\Services\Audit\AuditService;
use App\Services\Settings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class SystemSettingsController extends Controller
{
    public function edit(SystemSettings $settings): View
    {
        return view('admin.settings.edit', [
            'settings' => $settings->all(),
            'trialPlans' => Plan::query()->where('is_active', true)->orderBy('price')->get(),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request, SystemSettings $settings, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $before = $settings->all();
        $values = collect($data)->except(['logo', 'favicon'])->all();
        $values['maintenance_mode'] = $request->boolean('maintenance_mode');
        $newFiles = [];

        try {
            foreach (['logo', 'favicon'] as $kind) {
                if ($request->hasFile($kind)) {
                    $file = $request->file($kind);
                    $path = $file->storeAs('branding', Str::uuid().'.'.$file->extension(), 'local');
                    if (! $path) {
                        throw new \RuntimeException('Gambar merek tidak dapat disimpan.');
                    }
                    $newFiles[] = $path;
                    $values[$kind.'_path'] = $path;
                }
            }

            DB::transaction(function () use ($settings, $values, $audit, $request, $before): void {
                $settings->setMany($values);
                $audit->record('system.settings_updated', $request->user(), $before, $settings->all());
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($newFiles);
            throw $error;
        }

        foreach (['logo_path', 'favicon_path'] as $key) {
            if (isset($values[$key]) && is_string($before[$key]) && Str::startsWith($before[$key], 'branding/')) {
                Storage::disk('local')->delete($before[$key]);
            }
        }

        return back()->with('success', 'Pengaturan sistem berhasil disimpan.');
    }
}
