<?php

namespace App\Services\Subscription;

use App\Models\Feature;
use App\Models\FeatureUsage;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\AllModelsUnavailableException;
use App\Services\Notifications\NotificationService;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QuotaService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly GeneratorGuard $generatorGuard,
    ) {}

    public function runGeneration(User $user, string $featureCode, Closure $operation): mixed
    {
        $this->generatorGuard->begin($user, $featureCode, 'generation');
        try {
            $result = $this->runWithQuota($user, $featureCode, 'generation', $operation);
        } catch (DomainException $error) {
            $this->generatorGuard->failed($user, true);
            $this->notifications->generationFailed($user, $featureCode);
            throw $error;
        } catch (Throwable $error) {
            $this->generatorGuard->failed($user, false);
            $this->notifications->generationFailed($user, $featureCode);
            throw $error;
        }

        $this->generatorGuard->succeeded($user);

        try {
            $featureName = Feature::query()->where('code', $featureCode)->value('name') ?? 'Dokumen';
            $this->notifications->generationSucceeded($user, $featureName, $result);
            $this->notifications->nearQuota($user, $featureCode, $this);
        } catch (Throwable $error) {
            Log::warning('Generation notification failed', ['feature' => $featureCode, 'user_id' => $user->id, 'exception' => $error]);
        }

        return $result;
    }

    public function runRegeneration(User $user, string $featureCode, Closure $operation): mixed
    {
        $this->generatorGuard->begin($user, $featureCode, 'regeneration');
        try {
            $result = $this->runWithQuota($user, $featureCode, 'regeneration', $operation);
        } catch (DomainException $error) {
            $this->generatorGuard->failed($user, true);
            throw $error;
        } catch (Throwable $error) {
            $this->generatorGuard->failed($user, false);
            throw $error;
        }

        $this->generatorGuard->succeeded($user);

        return $result;
    }

    private function runWithQuota(User $user, string $featureCode, string $kind, Closure $operation): mixed
    {
        $result = DB::transaction(function () use ($user, $featureCode, $kind, $operation) {
            $subscription = Subscription::query()
                ->with('plan.features')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->latest('expires_at')
                ->first();

            if (! $subscription) {
                throw new SubscriptionAccessException('Masa aktif paket Anda telah berakhir.');
            }

            $feature = Feature::query()->where('code', $featureCode)->firstOrFail();
            $planFeature = $subscription->plan->features->firstWhere('id', $feature->id);

            if (! $planFeature || ! $planFeature->pivot->is_enabled) {
                throw new SubscriptionAccessException('Fitur ini belum termasuk dalam paket Anda.');
            }

            $usage = FeatureUsage::query()
                ->where('subscription_id', $subscription->id)
                ->where('feature_id', $feature->id)
                ->whereDate('period_start', $subscription->starts_at->toDateString())
                ->first();

            $usageWasCreated = ! $usage;
            if ($usageWasCreated) {
                $usage = FeatureUsage::query()->create([
                    'subscription_id' => $subscription->id,
                    'feature_id' => $feature->id,
                    'period_start' => $subscription->starts_at->toDateString(),
                    'period_end' => $subscription->expires_at->toDateString(),
                    'generation_count' => 0,
                    'regeneration_count' => 0,
                ]);
            }

            $usage = FeatureUsage::query()->lockForUpdate()->findOrFail($usage->id);
            $countField = $kind.'_count';
            $limitField = $kind.'_limit';
            $limit = $planFeature->pivot->{$limitField};

            if ($limit !== null && $usage->{$countField} >= $limit) {
                $activity = $kind === 'generation' ? 'pembuatan' : 'pembuatan ulang';
                throw new SubscriptionAccessException("Batas {$activity} {$feature->name} pada paket Anda telah tercapai.");
            }

            try {
                $result = $operation($feature, $subscription);
            } catch (AllModelsUnavailableException $error) {
                // Commit provider health and failed-attempt diagnostics without consuming quota.
                if ($usageWasCreated) {
                    $usage->delete();
                }

                return $error;
            }
            $usage->increment($countField);

            return $result;
        }, 3);

        if ($result instanceof AllModelsUnavailableException) {
            throw $result;
        }

        return $result;
    }

    public function summary(User $user): array
    {
        $subscription = Subscription::query()
            ->with(['plan.features', 'usages'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        if (! $subscription) {
            return ['subscription' => null, 'features' => collect()];
        }

        $features = $subscription->plan->features->map(function (Feature $feature) use ($subscription) {
            $usage = $subscription->usages->firstWhere('feature_id', $feature->id);
            $used = $usage?->generation_count ?? 0;
            $limit = $feature->pivot->generation_limit;
            $regenerationUsed = $usage?->regeneration_count ?? 0;
            $regenerationLimit = $feature->pivot->regeneration_limit;

            return [
                'code' => $feature->code,
                'name' => $feature->name,
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit === null ? null : max(0, $limit - $used),
                'percentage' => $limit ? min(100, round(($used / $limit) * 100)) : 0,
                'regeneration_used' => $regenerationUsed,
                'regeneration_limit' => $regenerationLimit,
                'regeneration_remaining' => $regenerationLimit === null ? null : max(0, $regenerationLimit - $regenerationUsed),
            ];
        });

        return compact('subscription', 'features');
    }
}
