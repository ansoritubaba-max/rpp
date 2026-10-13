<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiRoute;
use App\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OpenRouterBalancedModelsSeeder extends Seeder
{
    public function run(): void
    {
        $provider = AiProvider::query()->where('provider_type', 'openrouter')->first();
        $silabus = Feature::query()->where('code', 'silabus')->first();

        if (! $provider || ! $silabus) {
            $this->command?->warn('Provider OpenRouter atau fitur Silabus belum tersedia.');

            return;
        }

        DB::transaction(function () use ($provider, $silabus): void {
            $models = collect([
                [
                    'name' => 'Qwen 3.5 9B · OpenRouter',
                    'model_key' => 'qwen/qwen3.5-9b',
                    'priority' => 7,
                    'estimated_input_cost' => 0.10,
                    'estimated_output_cost' => 0.15,
                ],
                [
                    'name' => 'Qwen 3.5 Flash · OpenRouter',
                    'model_key' => 'qwen/qwen3.5-flash-02-23',
                    'priority' => 8,
                    'estimated_input_cost' => 0.065,
                    'estimated_output_cost' => 0.26,
                ],
            ])->mapWithKeys(function (array $settings) use ($provider): array {
                $model = AiModel::query()->firstOrCreate(
                    ['provider_id' => $provider->id, 'model_key' => $settings['model_key']],
                    [
                        'name' => $settings['name'],
                        'priority' => $settings['priority'],
                        'status' => 'active',
                        'max_output_tokens' => 4096,
                        'timeout_seconds' => 60,
                        'cooldown_seconds' => 60,
                        'estimated_input_cost' => $settings['estimated_input_cost'],
                        'estimated_output_cost' => $settings['estimated_output_cost'],
                    ],
                );

                return [$settings['model_key'] => $model];
            });

            $existingModelIds = AiRoute::query()->where('feature_id', $silabus->id)
                ->whereIn('ai_model_id', $models->pluck('id'))
                ->pluck('ai_model_id');

            if ($existingModelIds->count() === $models->count()) {
                return;
            }

            if ($existingModelIds->isNotEmpty()) {
                foreach ($models as $model) {
                    if (! $existingModelIds->contains($model->id)) {
                        AiRoute::query()->create([
                            'feature_id' => $silabus->id,
                            'ai_model_id' => $model->id,
                            'priority' => AiRoute::query()->where('feature_id', $silabus->id)->max('priority') + 1,
                            'is_active' => true,
                        ]);
                    }
                }

                return;
            }

            // Preserve the existing order, inserting both tested models after GPT-OSS 120B.
            AiRoute::query()->where('feature_id', $silabus->id)
                ->where('priority', '>=', 3)
                ->increment('priority', 2);

            foreach (['qwen/qwen3.5-9b' => 3, 'qwen/qwen3.5-flash-02-23' => 4] as $key => $priority) {
                AiRoute::query()->firstOrCreate(
                    ['feature_id' => $silabus->id, 'ai_model_id' => $models[$key]->id],
                    ['priority' => $priority, 'is_active' => true],
                );
            }
        });
    }
}
