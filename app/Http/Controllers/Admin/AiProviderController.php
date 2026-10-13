<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiProviderRequest;
use App\Models\AiProvider;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AiProviderController extends Controller
{
    public function index(): View
    {
        return view('admin.ai.providers.index', [
            'providers' => AiProvider::query()->withCount('models')->orderByDesc('status')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.ai.providers.form', ['provider' => new AiProvider]);
    }

    public function store(AiProviderRequest $request, AuditService $auditService): RedirectResponse
    {
        $provider = AiProvider::query()->create($this->attributes($request));
        $auditService->record('ai.provider_created', $provider, [], $this->snapshot($provider));

        return redirect()->route('admin.ai.providers.index')->with('success', 'Provider AI berhasil ditambahkan.');
    }

    public function edit(AiProvider $provider): View
    {
        return view('admin.ai.providers.form', compact('provider'));
    }

    public function update(AiProviderRequest $request, AiProvider $provider, AuditService $auditService): RedirectResponse
    {
        $before = $this->snapshot($provider);
        $provider->update($this->attributes($request, true));
        $auditService->record('ai.provider_updated', $provider, $before, $this->snapshot($provider->fresh()));

        return redirect()->route('admin.ai.providers.index')->with('success', 'Provider AI berhasil diperbarui.');
    }

    private function attributes(AiProviderRequest $request, bool $updating = false): array
    {
        $attributes = $request->safe()->only(['name', 'provider_type', 'base_url', 'status', 'timeout_seconds', 'notes']);
        if (! $updating || $request->filled('api_key')) {
            $attributes['api_key'] = $request->string('api_key')->toString();
        }

        return $attributes;
    }

    private function snapshot(AiProvider $provider): array
    {
        return $provider->only(['name', 'provider_type', 'base_url', 'status', 'timeout_seconds', 'notes']);
    }
}
