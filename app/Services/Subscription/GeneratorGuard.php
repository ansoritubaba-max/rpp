<?php

namespace App\Services\Subscription;

use App\Models\GeneratorAbuseState;
use App\Models\OperationalEvent;
use App\Models\User;
use App\Services\Monitoring\OperationalEventRecorder;
use App\Services\Settings\SystemSettings;
use DomainException;
use Illuminate\Support\Facades\RateLimiter;

class GeneratorGuard
{
    public function __construct(
        private readonly SystemSettings $settings,
        private readonly OperationalEventRecorder $events,
    ) {}

    public function begin(User $user, string $featureCode, string $kind): void
    {
        $config = $this->settings->all();
        $ip = request()->ip();
        $fingerprint = $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
        $state = GeneratorAbuseState::query()->firstOrCreate(['user_id' => $user->id], ['status' => 'normal']);
        $this->clearExpiredState($state);

        if ($state->status === 'temporary_block' && $state->blocked_until?->isFuture()) {
            throw new DomainException('Pembuatan dokumen sementara diblokir karena aktivitas tidak wajar. Coba lagi setelah '.$state->blocked_until->locale('id')->translatedFormat('H:i').'.');
        }

        $recent = OperationalEvent::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subMinutes(10));
        $rejections = (clone $recent)->where('type', 'generator_rejected')->count();
        if ($rejections >= (int) $config['generator_failure_threshold']) {
            $this->block($state, 'excessive_failures', $ip, (int) $config['generator_block_minutes']);
        }

        if ($fingerprint) {
            $knownIps = (clone $recent)->where('type', 'generator_attempt')->whereNotNull('ip_fingerprint')
                ->distinct()->pluck('ip_fingerprint');
            if (! $knownIps->contains($fingerprint) && $knownIps->count() >= (int) $config['generator_distinct_ip_threshold'] - 1) {
                $this->block($state, 'many_ips', $ip, (int) $config['generator_block_minutes']);
            }
        }

        $userId = $user->id;
        $limits = [
            ['generator:user:'.$userId, (int) $config['generator_user_per_minute'], 60],
            ['generator:feature:'.$userId.':'.$featureCode, (int) $config['generator_feature_per_minute'], 60],
            ['generator:ip:'.($fingerprint ?? 'unknown'), (int) $config['generator_ip_per_minute'], 60],
            ['generator:endpoint:'.$kind.':'.($fingerprint ?? 'unknown'), (int) $config['generator_endpoint_per_minute'], 60],
        ];
        $burst = ['generator:burst:'.$userId, (int) $config['generator_burst_per_10_seconds'], 10];
        if (RateLimiter::tooManyAttempts($burst[0], $burst[1])) {
            $this->block($state, 'automation_burst', $ip, (int) $config['generator_block_minutes']);
        }

        foreach ($limits as [$key, $limit]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $this->events->record('generator_limited', $userId, ipAddress: $ip);
                $this->warn($state, 'rate_limit', $ip);
                $denied = RateLimiter::hit('generator:denied:'.$userId, 600);
                if ($denied >= (int) $config['generator_failure_threshold']) {
                    $this->block($state, 'endpoint_spam', $ip, (int) $config['generator_block_minutes']);
                }
                $wait = max(1, RateLimiter::availableIn($key));
                throw new DomainException("Terlalu banyak permintaan pembuatan dokumen. Coba lagi dalam {$wait} detik.");
            }
        }

        foreach ([$burst, ...$limits] as [$key, , $seconds]) {
            RateLimiter::hit($key, $seconds);
        }
        $this->events->record('generator_attempt', $userId, ipAddress: $ip);

        if (RateLimiter::attempts($limits[0][0]) >= (int) ceil($limits[0][1] * .75)
            || RateLimiter::attempts($limits[1][0]) >= (int) ceil($limits[1][1] * .75)) {
            $this->warn($state, 'high_frequency', $ip);
        }
    }

    public function succeeded(User $user): void
    {
        $this->events->record('generator_success', $user->id, ipAddress: request()->ip());
    }

    public function failed(User $user, bool $userCaused): void
    {
        $ip = request()->ip();
        $this->events->record($userCaused ? 'generator_rejected' : 'generator_failed', $user->id, ipAddress: $ip);
        if (! $userCaused) {
            return;
        }

        $threshold = (int) $this->settings->get('generator_failure_threshold');
        $rejections = OperationalEvent::query()->where('user_id', $user->id)->where('type', 'generator_rejected')
            ->where('created_at', '>=', now()->subMinutes(10))->count();
        if ($rejections >= $threshold) {
            $state = GeneratorAbuseState::query()->firstOrCreate(['user_id' => $user->id], ['status' => 'normal']);
            $this->block($state, 'excessive_failures', $ip, (int) $this->settings->get('generator_block_minutes'));
        }
    }

    private function clearExpiredState(GeneratorAbuseState $state): void
    {
        if (($state->status === 'temporary_block' && $state->blocked_until?->isPast())
            || ($state->status === 'warning' && $state->warning_until?->isPast())) {
            $state->update(['status' => 'normal', 'reason' => null, 'warning_until' => null, 'blocked_until' => null]);
        }
    }

    private function warn(GeneratorAbuseState $state, string $reason, ?string $ip): void
    {
        if ($state->status !== 'normal') {
            return;
        }

        $state->update(['status' => 'warning', 'reason' => $reason, 'warning_until' => now()->addMinutes(10)]);
        $this->events->record('generator_warning', $state->user_id, ipAddress: $ip);
    }

    private function block(GeneratorAbuseState $state, string $reason, ?string $ip, int $minutes): never
    {
        $state->update([
            'status' => 'temporary_block',
            'reason' => $reason,
            'warning_until' => null,
            'blocked_until' => now()->addMinutes($minutes),
        ]);
        $this->events->record('generator_blocked', $state->user_id, ipAddress: $ip);

        throw new DomainException("Pembuatan dokumen sementara diblokir karena aktivitas tidak wajar. Coba lagi dalam {$minutes} menit.");
    }
}
