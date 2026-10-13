@extends('layouts.app', ['title' => 'Pembayaran Paket'])

@section('content')
<div class="mx-auto max-w-2xl">
    <a href="{{ route('subscription.index') }}" class="text-sm font-bold text-brand-700">← Kembali ke paket</a>
    <section class="surface mt-5 overflow-hidden">
        <div class="bg-gradient-to-r from-brand-700 to-indigo-700 px-6 py-7 text-white sm:px-8">
            <p class="text-xs font-extrabold uppercase tracking-[.2em] text-blue-100">Checkout aman · RuangAjar</p>
            <h1 class="mt-2 text-2xl font-black">{{ $payment->plan->name }}</h1>
            <p class="mt-2 text-3xl font-black">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
        </div>
        <div class="p-6 sm:p-8">
            @if($errors->has('checkout'))
                <p role="alert" class="mb-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">{{ $errors->first('checkout') }}</p>
            @endif
            @if($payment->status === 'paid')
                <p class="rounded-xl bg-emerald-50 p-4 font-bold text-emerald-700">Pembayaran terkonfirmasi. Paket Anda sudah aktif.</p>
                <a href="{{ route('dashboard') }}" class="btn-primary mt-5">Ke dashboard</a>
            @elseif($payment->status === 'pending' && $payment->snap_token && $payment->expires_at?->isFuture())
                <p class="text-sm text-slate-600">Pilih metode pembayaran di jendela Midtrans. Anda bisa menggunakan QRIS atau metode lain yang tersedia untuk akun merchant ini.</p>
                <button type="button" id="pay-button" class="btn-primary mt-6 w-full justify-center">Buka pembayaran aman</button>
                <p id="payment-message" role="status" class="mt-4 hidden rounded-xl bg-blue-50 p-4 text-sm text-blue-800"></p>
                <p class="mt-5 text-xs text-slate-500">Paket hanya aktif setelah Midtrans mengonfirmasi pembayaran. Menutup jendela tidak membatalkan tagihan yang sudah dibuat.</p>
                <form id="check-form" method="POST" action="{{ route('checkout.check', $payment) }}" class="mt-5">@csrf<button class="btn-secondary w-full justify-center">Periksa status pembayaran</button></form>
                <script src="{{ config('services.midtrans.production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}" defer></script>
                <script>
                    document.getElementById('pay-button').addEventListener('click', function () {
                        const message = document.getElementById('payment-message');
                        if (!window.snap || typeof window.snap.pay !== 'function') {
                            message.textContent = 'Halaman pembayaran belum termuat. Periksa koneksi lalu coba lagi.';
                            message.classList.remove('hidden');
                            return;
                        }
                        window.snap.pay({{ Illuminate\Support\Js::from($payment->snap_token) }}, {
                            onSuccess: function () { document.getElementById('check-form').requestSubmit(); },
                            onPending: function () {
                                message.textContent = 'Pembayaran masih menunggu. Selesaikan instruksi dari Midtrans, lalu periksa status.';
                                message.classList.remove('hidden');
                            },
                            onError: function () {
                                message.textContent = 'Percobaan pembayaran belum berhasil. Anda dapat mencoba lagi atau memilih metode lain.';
                                message.classList.remove('hidden');
                            },
                            onClose: function () {
                                message.textContent = 'Jendela pembayaran ditutup. Anda dapat membukanya kembali kapan saja sebelum masa checkout berakhir.';
                                message.classList.remove('hidden');
                            }
                        });
                    });
                </script>
            @elseif($payment->status === 'pending' && $payment->qr_url && $payment->expires_at?->isFuture())
                <p class="text-sm text-slate-600">Transaksi QRIS lama Anda masih dapat diselesaikan sampai masa berlakunya habis.</p>
                <img src="{{ $payment->qr_url }}" alt="QRIS untuk pembayaran {{ $payment->plan->name }}" class="mx-auto mt-5 h-64 w-64 rounded-xl border border-slate-200 bg-white p-2">
                <form method="POST" action="{{ route('checkout.check', $payment) }}" class="mt-5">@csrf<button class="btn-secondary w-full justify-center">Periksa status</button></form>
            @else
                <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">{{ $payment->status === 'pending' ? 'Masa checkout berakhir. Jika sudah membayar, periksa status sebelum membuat transaksi baru.' : 'Transaksi ini tidak aktif. Pilih paket lagi untuk membuat checkout baru.' }}</p>
                @if($payment->status === 'pending' && ($payment->snap_token || $payment->qr_url))
                    <form method="POST" action="{{ route('checkout.check', $payment) }}" class="mt-5">@csrf<button class="btn-secondary">Periksa status di Midtrans</button></form>
                @endif
                <a href="{{ route('subscription.index') }}#pilih-paket" class="mt-4 inline-flex text-sm font-bold text-brand-700">Pilih paket lagi →</a>
            @endif
            <p class="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-400">Kode pesanan: {{ $payment->gateway_order_id }}</p>
        </div>
    </section>
</div>
@endsection
