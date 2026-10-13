<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Services\Subscription\MidtransQrisException;
use App\Services\Subscription\MidtransQrisService;
use App\Services\Subscription\MidtransSnapService;
use App\Services\Subscription\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class TeacherCheckoutController extends Controller
{
    public function store(Request $request, Plan $plan, MidtransSnapService $snap): RedirectResponse
    {
        abort_unless($request->user()->role === 'guru' && $plan->is_active, 404);
        if (! $snap->configured()) {
            return back()->withErrors(['checkout' => 'Pembayaran belum tersedia. Hubungi admin.']);
        }

        $amount = $plan->currentPrice();
        if ($amount < 1) {
            return back()->withErrors(['checkout' => 'Paket gratis tidak memerlukan pembayaran. Hubungi admin.']);
        }

        $pending = $request->user()->payments()
            ->where('plan_id', $plan->id)->where('gateway', 'midtrans')
            ->where('status', 'pending')->where('amount', $amount)
            ->where('expires_at', '>', now())->whereNotNull('snap_token')->latest()->first();
        if ($pending) {
            return redirect()->route('checkout.show', $pending);
        }

        $payment = $request->user()->payments()->create([
            'plan_id' => $plan->id,
            'amount' => $amount,
            'payment_method' => 'Midtrans Snap',
            'payment_date' => now()->toDateString(),
            'status' => 'pending',
            'gateway' => 'midtrans',
            'gateway_order_id' => 'RA-'.Str::upper((string) Str::ulid()),
        ]);

        try {
            $snap->createToken($payment->load(['plan', 'user']));
        } catch (MidtransQrisException $error) {
            $payment->update(['status' => 'cancelled']);

            return back()->withErrors(['checkout' => $error->userMessage]);
        } catch (Throwable $error) {
            $payment->update([
                'status' => 'cancelled',
                'gateway_error_code' => 'CONNECTION_FAILED',
                'gateway_error_message' => 'Koneksi ke Midtrans gagal atau respons tidak tersedia.',
            ]);
            Log::warning('Teacher Snap checkout connection failed', ['payment_id' => $payment->id, 'exception_type' => $error::class]);

            return back()->withErrors(['checkout' => 'Halaman pembayaran belum dapat dibuka. Silakan coba lagi nanti.']);
        }

        return redirect()->route('checkout.show', $payment);
    }

    public function show(Request $request, Payment $payment): View
    {
        abort_unless($request->user()->id === $payment->user_id && $payment->gateway === 'midtrans', 404);

        return view('subscriptions.checkout', ['payment' => $payment->load('plan')]);
    }

    public function check(Request $request, Payment $payment, MidtransQrisService $qris, PaymentService $payments): RedirectResponse
    {
        abort_unless($request->user()->id === $payment->user_id && $payment->gateway === 'midtrans', 404);

        try {
            $qris->refreshStatus($payment, $payments);
        } catch (Throwable $error) {
            Log::warning('Teacher Midtrans status check failed', ['payment_id' => $payment->id, 'exception_type' => $error::class]);

            return back()->withErrors(['checkout' => $error instanceof MidtransQrisException
                ? $error->userMessage
                : 'Status pembayaran belum dapat diperiksa. Coba beberapa saat lagi.']);
        }

        return redirect()->route('checkout.show', $payment)->with('success', 'Status pembayaran telah diperbarui.');
    }
}
