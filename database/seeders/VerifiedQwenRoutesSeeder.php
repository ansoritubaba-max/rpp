<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiRoute;
use App\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VerifiedQwenRoutesSeeder extends Seeder
{
    public function run(): void
    {
        $models = AiModel::query()->whereIn('model_key', [
            'qwen/qwen3.5-9b', 'qwen/qwen3.5-flash-02-23',
        ])->get()->keyBy('model_key');

        foreach ([
            'rpp' => ['qwen/qwen3.5-9b', 'qwen/qwen3.5-flash-02-23'],
            'modul_ajar' => ['qwen/qwen3.5-9b'],
            'atp' => ['qwen/qwen3.5-9b'],
            'kisi_kisi' => ['qwen/qwen3.5-9b'],
        ] as $featureCode => $modelKeys) {
            $feature = Feature::query()->where('code', $featureCode)->first();
            if (! $feature || collect($modelKeys)->contains(fn ($key) => ! $models->has($key))) {
                continue;
            }

            DB::transaction(function () use ($feature, $modelKeys, $models): void {
                $missing = collect($modelKeys)->reject(fn ($key) => AiRoute::query()
                    ->where('feature_id', $feature->id)
                    ->where('ai_model_id', $models[$key]->id)
                    ->exists())->values();
                if ($missing->isEmpty()) {
                    return;
                }

                AiRoute::query()->where('feature_id', $feature->id)
                    ->where('priority', '>=', 3)
                    ->increment('priority', $missing->count());

                foreach ($missing as $index => $key) {
                    AiRoute::query()->create([
                        'feature_id' => $feature->id,
                        'ai_model_id' => $models[$key]->id,
                        'priority' => 3 + $index,
                        'is_active' => true,
                    ]);
                }
            });
        }
    }
}
