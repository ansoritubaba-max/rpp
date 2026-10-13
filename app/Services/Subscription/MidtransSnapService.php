<?php

namespace App\Services\Subscription;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransSnapService
{
    public function configured(): bool
    {
        return filled(config('services.midtrans.server_key'))
            && filled(config('services.midtrans.client_key'));
    }

    public function createToken(Payment $payment): void
    {
        if (! $this->configured()) {
            throw new MidtransQrisException('Midtrans Snap keys are not configured.', 'Pembayaran sedang dikonfigurasi. Hubungi admin.');
        }

        $baseUrl = config('services.midtrans.production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';

        $response = Http::withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()->asJson()->timeout(15)
            ->post($baseUrl.'/snap/v1/transactions', [
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
                'page_expiry' => ['duration' => 1, 'unit' => 'days'],
            ]);

        $data = $response->json();
        $data = is_array($data) ? $data : [];
        $token = $data['token'] ?? null;

        if (! $response->successful() || ! is_string($token) || ! preg_match('/^[A-Za-z0-9-]{20,100}$/', $token)) {
            $message = mb_substr((string) (($data['error_messages'][0] ?? null) ?: ($data['status_message'] ?? 'Token pembayaran tidak tersedia.')), 0, 255);
            $code = $response->status() === 401 ? 'AUTH_REJECTED' : 'SNAP_TOKEN_REJECTED';
            $payment->update(['gateway_error_code' => $code, 'gateway_error_message' => $message]);
            Log::warning('Midtrans Snap token rejected', [
                'payment_id' => $payment->id,
                'http_status' => $response->status(),
                'error_code' => $code,
                'gateway_message' => $message,
            ]);

            throw new MidtransQrisException($code, 'Halaman pembayaran belum dapat dibuka. Silakan coba lagi nanti.');
        }

        $payment->update([
            'snap_token' => $token,
            'expires_at' => now()->addDay(),
            'gateway_error_code' => null,
            'gateway_error_message' => null,
        ]);
    }
}
