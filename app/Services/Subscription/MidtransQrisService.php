<?php

namespace App\Services\Subscription;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransQrisService
{
    public function configured(): bool
    {
        return filled(config('services.midtrans.server_key'));
    }

    public function charge(Payment $payment): void
    {
        if (! $this->configured()) {
            throw new MidtransQrisException('Midtrans Server Key is not configured.', 'Pembayaran QRIS belum tersedia. Hubungi admin.');
        }

        $response = Http::withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()->asJson()->timeout(15)->post($this->baseUrl().'/v2/charge', [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $payment->gateway_order_id,
                    'gross_amount' => (int) $payment->amount,
                ],
                'item_details' => [[
                    'id' => 'plan-'.$payment->plan_id,
                    'price' => (int) $payment->amount,
                    'quantity' => 1,
                    'name' => mb_substr($payment->plan->name, 0, 50),
                ]],
                'customer_details' => [
                    'first_name' => $payment->user->name,
                    'email' => $payment->user->email,
                ],
                'qris' => ['acquirer' => 'gopay'],
            ]);

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $action = collect($body['actions'] ?? [])->firstWhere('name', 'generate-qr-code');
        $qrUrl = $action['url'] ?? null;
        if (! $response->successful() || ($body['transaction_status'] ?? null) !== 'pending'
            || ($body['order_id'] ?? null) !== $payment->gateway_order_id
            || ! $this->safeQrUrl($qrUrl)) {
            $gatewayMessage = mb_substr((string) ($body['status_message'] ?? $body['error'] ?? 'Respons QRIS tidak valid.'), 0, 255);
            $errorCode = $this->chargeErrorCode($response->status(), $gatewayMessage);
            $payment->update([
                'gateway_error_code' => $errorCode,
                'gateway_error_message' => $gatewayMessage,
            ]);
            Log::warning('Midtrans QRIS charge rejected', [
                'payment_id' => $payment->id,
                'http_status' => $response->status(),
                'gateway_status_code' => $body['status_code'] ?? null,
                'error_code' => $errorCode,
                'gateway_message' => $gatewayMessage,
            ]);

            $userMessage = in_array($errorCode, ['AUTH_REJECTED', 'MERCHANT_QRIS_NOT_READY'], true)
                ? 'Pembayaran QRIS sedang dikonfigurasi. Silakan hubungi admin.'
                : 'QRIS belum berhasil dibuat. Silakan coba lagi nanti.';

            throw new MidtransQrisException($errorCode, $userMessage);
        }

        $payment->update([
            'gateway_transaction_id' => $body['transaction_id'] ?? null,
            'qr_url' => $qrUrl,
            'expires_at' => now()->addMinutes(15),
            'gateway_error_code' => null,
            'gateway_error_message' => null,
        ]);
    }

    public function refreshStatus(Payment $payment, PaymentService $payments): void
    {
        if (! $this->configured() || $payment->gateway !== 'midtrans' || ! $payment->gateway_order_id || (! $payment->qr_url && ! $payment->snap_token)) {
            throw new MidtransQrisException('Payment cannot be checked.', 'Status pembayaran belum dapat diperiksa. Hubungi admin.');
        }

        $response = Http::withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()->timeout(15)
            ->get($this->baseUrl().'/v2/'.rawurlencode($payment->gateway_order_id).'/status');
        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ! $this->matchesPayment($data, $payment)) {
            Log::warning('Midtrans QRIS status check failed', [
                'payment_id' => $payment->id,
                'http_status' => $response->status(),
                'gateway_status_code' => is_array($data) ? ($data['status_code'] ?? null) : null,
            ]);
            throw new MidtransQrisException('Invalid Midtrans status response.', 'Status pembayaran belum dapat diperiksa. Coba beberapa saat lagi.');
        }

        $this->applyStatus($payment, $data, $payments);
    }

    public function acceptNotification(array $data, PaymentService $payments): bool
    {
        $key = (string) config('services.midtrans.server_key');
        $orderId = $data['order_id'] ?? null;
        $statusCode = $data['status_code'] ?? null;
        $gross = $data['gross_amount'] ?? null;
        $signature = $data['signature_key'] ?? null;
        if ($key === '' || ! is_string($orderId) || ! is_string($statusCode)
            || ! is_string($gross) || ! is_string($signature)
            || ! hash_equals(hash('sha512', $orderId.$statusCode.$gross.$key), $signature)) {
            return false;
        }

        $payment = Payment::query()->where('gateway', 'midtrans')->where('gateway_order_id', $orderId)->first();
        if (! $payment || ! $this->matchesPayment($data, $payment)) {
            return false;
        }

        $this->applyStatus($payment, $data, $payments);

        return true;
    }

    private function matchesPayment(array $data, Payment $payment): bool
    {
        $gross = $data['gross_amount'] ?? null;

        return ($data['order_id'] ?? null) === $payment->gateway_order_id
            && ($payment->snap_token
                ? is_string($data['payment_type'] ?? null) && $data['payment_type'] !== ''
                : ($data['payment_type'] ?? null) === 'qris')
            && is_string($gross)
            && preg_match('/^\d+\.\d{2}$/', $gross) === 1
            && $gross === number_format((float) $payment->amount, 2, '.', '')
            && (! $payment->gateway_transaction_id || ($data['transaction_id'] ?? $payment->gateway_transaction_id) === $payment->gateway_transaction_id)
            && (! filled(config('services.midtrans.merchant_id')) || ($data['merchant_id'] ?? null) === config('services.midtrans.merchant_id'));
    }

    private function applyStatus(Payment $payment, array $data, PaymentService $payments): void
    {
        $status = $data['transaction_status'] ?? null;
        if (in_array($status, ['settlement', 'capture'], true) && ($data['fraud_status'] ?? 'accept') === 'accept') {
            $payments->markPaid($payment);
        } elseif (in_array($status, ['refund', 'chargeback'], true)) {
            $payments->markRefunded($payment);
        } elseif (($status === 'expire' || (! $payment->snap_token && in_array($status, ['cancel', 'deny'], true))) && $payment->status === 'pending') {
            $payment->update(['status' => 'cancelled']);
        }
    }

    private function chargeErrorCode(int $httpStatus, string $message): string
    {
        if ($httpStatus === 401) {
            return 'AUTH_REJECTED';
        }
        if (str_contains(mb_strtolower($message), 'merchant pop id is not found')) {
            return 'MERCHANT_QRIS_NOT_READY';
        }

        return 'GATEWAY_REJECTED';
    }

    private function baseUrl(): string
    {
        return config('services.midtrans.production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    private function safeQrUrl(mixed $url): bool
    {
        if (! is_string($url) || strlen($url) > 2000) {
            return false;
        }
        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, ['api.midtrans.com', 'api.sandbox.midtrans.com'], true);
    }
}
