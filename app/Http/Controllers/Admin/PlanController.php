<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Feature;
use App\Models\Plan;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()->with('features')->withCount(['subscriptions as active_subscriptions_count' => fn ($query) => $query->where('status', 'active')->where('expires_at', '>', now())])->orderByDesc('is_active')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', ['plan' => new Plan, 'features' => Feature::query()->where('is_active', true)->get()]);
    }

    public function store(PlanRequest $request, AuditService $auditService): RedirectResponse
    {
        $this->ensureFeatureSelected($request->validated('features'));
        $plan = DB::transaction(function () use ($request) {
            $plan = Plan::query()->create($request->safe()->only(['name', 'description', 'price', 'discount_percent', 'discount_ends_at', 'reference_price', 'show_reference_price', 'is_recommended', 'sort_order', 'duration_days', 'is_active']));
            $plan->features()->sync($this->featureSync($request->validated('features')));

            return $plan;
        });
        $auditService->record('plan.created', $plan, [], $this->snapshot($plan));

        return redirect()->route('admin.plans.index')->with('success', 'Paket baru berhasil dibuat.');
    }

    public function edit(Plan $plan): View
    {
        $plan->load('features');

        return view('admin.plans.form', ['plan' => $plan, 'features' => Feature::query()->where('is_active', true)->get()]);
    }

    public function update(PlanRequest $request, Plan $plan, AuditService $auditService): RedirectResponse
    {
        $this->ensureFeatureSelected($request->validated('features'));
        $before = $this->snapshot($plan->load('features'));
        DB::transaction(function () use ($request, $plan) {
            $plan->update($request->safe()->only(['name', 'description', 'price', 'discount_percent', 'discount_ends_at', 'reference_price', 'show_reference_price', 'is_recommended', 'sort_order', 'duration_days', 'is_active']));
            $plan->features()->sync($this->featureSync($request->validated('features')));
        });
        $auditService->record('plan.updated', $plan, $before, $this->snapshot($plan->fresh('features')));

        return redirect()->route('admin.plans.index')->with('success', 'Paket berhasil diperbarui.');
    }

    private function featureSync(array $features): array
    {
        return collect($features)
            ->filter(fn ($settings) => (bool) ($settings['enabled'] ?? false))
            ->mapWithKeys(fn ($settings, $featureId) => [(int) $featureId => [
                'is_enabled' => true,
                'generation_limit' => filled($settings['generation_limit'] ?? null) ? (int) $settings['generation_limit'] : null,
                'regeneration_limit' => filled($settings['regeneration_limit'] ?? null) ? (int) $settings['regeneration_limit'] : null,
            ]])
            ->all();
    }

    private function ensureFeatureSelected(array $features): void
    {
        if (! collect($features)->contains(fn ($settings) => (bool) ($settings['enabled'] ?? false))) {
            throw ValidationException::withMessages(['features' => 'Pilih minimal satu fitur untuk paket ini.']);
        }
    }

    private function snapshot(Plan $plan): array
    {
        return $plan->only(['name', 'description', 'price', 'discount_percent', 'discount_ends_at', 'reference_price', 'show_reference_price', 'is_recommended', 'sort_order', 'duration_days', 'is_active']) + [
            'features' => $plan->features->mapWithKeys(fn ($feature) => [$feature->code => [
                'generation_limit' => $feature->pivot->generation_limit,
                'regeneration_limit' => $feature->pivot->regeneration_limit,
            ]])->all(),
        ];
    }
}
