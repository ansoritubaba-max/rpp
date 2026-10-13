<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentRequest;
use App\Http\Requests\Admin\PaymentStatusRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Subscription\PaymentService;
use App\Services\Subscription\MidtransSnapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request, MidtransSnapService $snap): View
    {
        $payments = Payment::query()
            ->with(['user', 'plan', 'verifier'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->latest('payment_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'totals' => Payment::query()->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount")->first(),
            'midtransConfigured' => $snap->configured(),
            'midtransProduction' => (bool) config('services.midtrans.production'),
        ]);
    }

    public function create(): View
    {
        return view('admin.payments.form', [
            'users' => User::query()->where('role', 'guru')->where('status', 'active')->orderBy('name')->get(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('price')->get(),
        ]);
    }

    public function store(PaymentRequest $request, PaymentService $paymentService, AuditService $auditService): RedirectResponse
    {
        $data = $request->safe()->except(['proof_file', 'status']);
        if ($request->hasFile('proof_file')) {
            $data['proof_file'] = $request->file('proof_file')->store('payment-proofs', 'local');
        }

        $payment = Payment::query()->create($data + ['status' => 'pending']);
        $auditService->record('payment.created', $payment, [], $payment->only(['user_id', 'plan_id', 'amount', 'payment_method', 'payment_date', 'status']));

        if ($request->validated('status') === 'paid') {
            $paymentService->markPaid($payment, $request->user());
        } elseif ($request->validated('status') === 'cancelled') {
            $paymentService->cancel($payment, $request->user());
        }

        return redirect()->route('admin.payments.index')->with('success', 'Pembayaran berhasil dicatat.');
    }

    public function updateStatus(PaymentStatusRequest $request, Payment $payment, PaymentService $paymentService): RedirectResponse
    {
        abort_if($payment->gateway !== null, 403, 'Status transaksi gateway hanya boleh diubah oleh notifikasi terverifikasi.');
        $request->validated('status') === 'paid'
            ? $paymentService->markPaid($payment, $request->user())
            : $paymentService->cancel($payment, $request->user());

        return back()->with('success', 'Status pembayaran berhasil diperbarui.');
    }

    public function downloadProof(Payment $payment): StreamedResponse
    {
        abort_unless($payment->proof_file && Storage::disk('local')->exists($payment->proof_file), 404);

        return Storage::disk('local')->download($payment->proof_file);
    }
}
