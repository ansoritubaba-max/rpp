<?php

namespace App\Services\Notifications;

use App\Models\Document;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Subscription\QuotaService;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function send(User $user, string $type, string $title, string $body, ?string $actionUrl = null, ?string $dedupeKey = null): ?UserNotification
    {
        try {
            $attributes = compact('type', 'title', 'body') + ['action_url' => $actionUrl, 'dedupe_key' => $dedupeKey];

            return $dedupeKey
                ? $user->notifications()->firstOrCreate(['dedupe_key' => $dedupeKey], $attributes)
                : $user->notifications()->create($attributes);
        } catch (Throwable $error) {
            Log::error('Notification could not be saved', ['type' => $type, 'user_id' => $user->id, 'exception' => $error]);

            return null;
        }
    }

    public function generationSucceeded(User $user, string $featureName, mixed $result): void
    {
        $url = $result instanceof Document ? route('documents.show', $result) : route('documents.index');
        $this->send($user, 'generation_success', 'Dokumen selesai dibuat', "{$featureName} Anda sudah siap ditinjau dan diunduh.", $url);
    }

    public function generationFailed(User $user, string $featureCode): void
    {
        $this->send($user, 'generation_failed', 'Dokumen belum berhasil dibuat', 'Periksa isian dan kuota Anda, lalu coba kembali. Jika masalah berlanjut, hubungi pengelola.', route('create.index'), 'generation-failed:'.$featureCode.':'.today()->toDateString());
    }

    public function nearQuota(User $user, string $featureCode, QuotaService $quotaService): void
    {
        $summary = $quotaService->summary($user);
        $subscription = $summary['subscription'];
        if (! $subscription) {
            return;
        }

        $feature = $summary['features']->firstWhere('code', $featureCode);
        if (! $feature || $feature['limit'] === null || $feature['used'] === 0) {
            return;
        }

        if ($feature['remaining'] <= max(1, (int) ceil($feature['limit'] * 0.2))) {
            $this->send($user, 'quota_low', 'Kuota hampir habis', "Sisa pembuatan {$feature['name']}: {$feature['remaining']} dari {$feature['limit']}.", route('subscription.index'), 'quota-low:'.$subscription->id.':'.$featureCode);
        }
    }

    public function expiringSubscription(User $user, Subscription $subscription): void
    {
        $days = max(0, (int) now()->diffInDays($subscription->expires_at, false));
        $this->send($user, 'subscription_expiring', 'Paket segera berakhir', "Masa aktif paket Anda berakhir dalam {$days} hari, pada {$subscription->expires_at->locale('id')->translatedFormat('d F Y')}.", route('subscription.index'), 'subscription-expiring:'.$subscription->id);
    }

    public function syncExpiring(User $user): void
    {
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->latest('expires_at')
            ->first();

        if ($subscription) {
            $this->expiringSubscription($user, $subscription);

            return;
        }

        if ($user->activeSubscription()->exists()) {
            return;
        }

        $expired = $user->subscriptions()->where('expires_at', '<=', now())
            ->whereIn('status', ['active', 'expired'])
            ->latest('expires_at')->first();
        if ($expired) {
            $this->send(
                $user,
                'subscription_expired',
                'Masa paket telah berakhir',
                'Pilih paket untuk kembali membuat dokumen. Dokumen lama Anda tetap tersimpan.',
                route('subscription.index'),
                'subscription-expired:'.$expired->id,
            );
        }
    }
}
