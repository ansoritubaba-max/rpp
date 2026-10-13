<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Services\Subscription\QuotaService;
use Illuminate\View\View;

class CreateHubController extends Controller
{
    public function __invoke(QuotaService $quotaService): View
    {
        $user = request()->user();
        $quota = $quotaService->summary($user);
        $features = Feature::query()->where('is_active', true)->get();
        $drafts = $user->wizardDrafts()
            ->whereIn('feature_code', $features->pluck('code'))
            ->latest('updated_at')
            ->get();

        return view('generators.index', [
            'features' => $features,
            'quota' => $quota,
            'quotaByCode' => $quota['features']->keyBy('code'),
            'drafts' => $drafts,
            'draftsByCode' => $drafts->keyBy('feature_code'),
        ]);
    }
}
