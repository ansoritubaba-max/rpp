<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiModelRequest;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AiModelController extends Controller
{
    public function index(): View
    {
        return view('admin.ai.models.index', [
            'models' => AiModel::query()->with(['provider', 'health'])->withCount('routes')->orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.ai.models.form', [
            'model' => new AiModel(['priority' => 1, 'cooldown_seconds' => 60]),
            'providers' => AiProvider::query()->orderByDesc('status')->orderBy('name')->get(),
        ]);
    }

    public function store(AiModelRequest $request, AuditService $auditService): RedirectResponse
    {
        $model = AiModel::query()->create($request->validated());
        $auditService->record('ai.model_created', $model, [], $this->snapshot($model));

        return redirect()->route('admin.ai.models.index')->with('success', 'Model AI berhasil ditambahkan.');
    }

    public function edit(AiModel $model): View
    {
        return view('admin.ai.models.form', [
            'model' => $model,
            'providers' => AiProvider::query()->orderByDesc('status')->orderBy('name')->get(),
        ]);
    }

    public function update(AiModelRequest $request, AiModel $model, AuditService $auditService): RedirectResponse
    {
        $before = $this->snapshot($model);
        $model->update($request->validated());
        $auditService->record('ai.model_updated', $model, $before, $this->snapshot($model->fresh()));

        return redirect()->route('admin.ai.models.index')->with('success', 'Model AI berhasil diperbarui.');
    }

    private function snapshot(AiModel $model): array
    {
        return $model->only([
            'provider_id', 'name', 'model_key', 'priority', 'status', 'max_output_tokens',
            'timeout_seconds', 'estimated_input_cost', 'estimated_output_cost',
            'internal_daily_limit', 'cooldown_seconds',
        ]);
    }
}
