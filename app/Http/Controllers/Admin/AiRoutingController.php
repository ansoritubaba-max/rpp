<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiRoutingRequest;
use App\Models\AiModel;
use App\Models\AiRoute;
use App\Models\Feature;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiRoutingController extends Controller
{
    public function index(): View
    {
        return view('admin.ai.routing.index', [
            'features' => Feature::query()->where('is_active', true)->with(['aiModels' => fn ($query) => $query->with('provider')])->orderBy('name')->get(),
            'models' => AiModel::query()->with('provider')->orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    public function update(AiRoutingRequest $request, AuditService $auditService): RedirectResponse
    {
        $routes = $request->validated('routes');
        $features = Feature::query()->whereIn('id', array_keys($routes))->get()->keyBy('id');
        $modelIds = collect($routes)->flatMap(fn (array $settings) => array_keys($settings))->map(fn ($id) => (int) $id)->unique();
        $validModelIds = AiModel::query()->whereKey($modelIds)->pluck('id')->flip();

        DB::transaction(function () use ($routes, $features, $validModelIds, $auditService) {
            foreach ($routes as $featureId => $modelSettings) {
                $feature = $features->get((int) $featureId);
                if (! $feature) {
                    continue;
                }

                $before = $this->snapshot($feature);
                foreach ($modelSettings as $modelId => $settings) {
                    if (! $validModelIds->has((int) $modelId)) {
                        continue;
                    }

                    if ((bool) $settings['enabled']) {
                        AiRoute::query()->updateOrCreate(
                            ['feature_id' => $feature->id, 'ai_model_id' => (int) $modelId],
                            ['priority' => (int) $settings['priority'], 'is_active' => true],
                        );
                    } else {
                        AiRoute::query()->where('feature_id', $feature->id)->where('ai_model_id', (int) $modelId)->delete();
                    }
                }

                $after = $this->snapshot($feature);
                if ($before !== $after) {
                    $auditService->record('ai.routing_updated', $feature, $before, $after);
                }
            }
        });

        return back()->with('success', 'Routing dan urutan failover berhasil disimpan.');
    }

    private function snapshot(Feature $feature): array
    {
        return ['routes' => AiRoute::query()
            ->where('feature_id', $feature->id)
            ->orderBy('priority')
            ->get(['ai_model_id', 'priority', 'is_active'])
            ->map(fn (AiRoute $route) => $route->only(['ai_model_id', 'priority', 'is_active']))
            ->all()];
    }
}
