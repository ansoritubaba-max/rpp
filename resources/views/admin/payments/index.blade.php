@extends('layouts.app', ['title' => 'Pembayaran'])

@section('content')
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><p class="eyebrow">Transaksi guru</p><h1 class="mt-1 text-3xl font-black text-ink-950">Pembayaran</h1><p class="mt-2 text-sm text-slate-500">Pembayaran Midtrans diverifikasi otomatis; transaksi manual tetap diperiksa admin.</p></div>
    <a href="{{ route('admin.payments.create') }}" class="btn-primary"><x-icon name="plus" /> Catat pembayaran manual</a>
</div>
<div class="mb-5 rounded-xl {{ $midtransConfigured ? 'bg-blue-50 text-blue-800' : 'bg-amber-50 text-amber-800' }} p-4 text-sm">
    Midtrans: <strong>{{ $midtransProduction ? 'Produksi' : 'Sandbox (uji coba)' }}</strong> · {{ $midtransConfigured ? 'Server Key terpasang.' : 'Server Key belum terpasang. Pembayaran Midtrans belum tersedia untuk guru.' }}
    @if(!$midtransProduction)<span class="block mt-1">Transaksi sandbox hanya muncul di dashboard Midtrans lingkungan Sandbox, bukan Production.</span>@endif
</div>

<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3">
    <div class="surface p-4"><p class="text-xs font-bold text-slate-400">Total transaksi</p><p class="mt-2 text-2xl font-black text-ink-950">{{ number_format($totals->total ?? 0) }}</p></div>
    <div class="surface p-4"><p class="text-xs font-bold text-slate-400">Menunggu pembayaran</p><p class="mt-2 text-2xl font-black text-amber-600">{{ number_format($totals->pending ?? 0) }}</p></div>
    <div class="surface col-span-2 p-4 md:col-span-1"><p class="text-xs font-bold text-slate-400">Total pembayaran lunas</p><p class="mt-2 text-2xl font-black text-emerald-700">Rp {{ number_format($totals->paid_amount ?? 0, 0, ',', '.') }}</p></div>
</div>

<form method="GET" class="surface mb-6 flex flex-col gap-3 p-4 sm:flex-row">
    <input name="search" value="{{ request('search') }}" class="field flex-1" placeholder="Cari guru...">
    <select name="status" class="field sm:max-w-52"><option value="">Semua status</option><option value="pending" @selected(request('status') === 'pending')>Pending</option><option value="paid" @selected(request('status') === 'paid')>Lunas</option><option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option><option value="refunded" @selected(request('status') === 'refunded')>Dikembalikan</option></select>
    <button class="btn-secondary">Terapkan</button>
</form>

<div class="surface overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
    <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-4">Tanggal / Guru</th><th class="px-5 py-4">Paket</th><th class="px-5 py-4">Nominal</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Tindakan</th></tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($payments as $payment)
            <tr>
                <td class="px-5 py-4"><p class="font-bold text-slate-800">{{ $payment->user->name }}</p><p class="text-xs text-slate-400">{{ $payment->payment_date->translatedFormat('d M Y') }} · {{ $payment->payment_method }}{{ $payment->gateway ? ' otomatis' : ' manual' }}</p></td>
                <td class="px-5 py-4"><p class="font-bold text-slate-700">{{ $payment->plan->name }}</p>@if($payment->proof_file)<a href="{{ route('admin.payments.proof', $payment) }}" class="text-xs font-bold text-brand-700">Lihat bukti</a>@endif</td>
                <td class="px-5 py-4 font-black text-slate-800">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                <td class="px-5 py-4"><span class="chip {{ ['paid' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-amber-50 text-amber-700', 'cancelled' => 'bg-rose-50 text-rose-700', 'refunded' => 'bg-slate-100 text-slate-600'][$payment->status] ?? 'bg-slate-100 text-slate-500' }}">{{ ['paid' => 'Lunas', 'pending' => 'Pending', 'cancelled' => 'Dibatalkan', 'refunded' => 'Dikembalikan'][$payment->status] ?? $payment->status }}</span>@if($payment->gateway_error_code)<p class="mt-2 text-xs font-semibold text-rose-700">{{ $payment->gateway_error_code }}</p><p class="mt-1 max-w-xs text-xs text-slate-500">{{ $payment->gateway_error_message }}</p>@endif</td>
                <td class="px-5 py-4">
                    @if($payment->gateway)
                        <span class="text-xs text-slate-500">{{ $payment->gateway_error_code ? 'Gagal membuat checkout Midtrans' : ($payment->status === 'pending' ? 'Menunggu konfirmasi gateway' : 'Otomatis oleh gateway') }}</span>@if($payment->gateway_order_id)<p class="mt-1 font-mono text-[10px] text-slate-400">{{ $payment->gateway_order_id }}</p>@endif
                    @elseif($payment->status === 'pending')
                        <div class="flex gap-2"><form method="POST" action="{{ route('admin.payments.status', $payment) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="paid"><button class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">Tandai lunas</button></form><form method="POST" action="{{ route('admin.payments.status', $payment) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">Batalkan</button></form></div>
                    @else
                        <span class="text-xs text-slate-400">{{ $payment->verifier?->name ? 'oleh '.$payment->verifier->name : 'Selesai' }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-6 py-14 text-center text-sm text-slate-400">Belum ada transaksi.</td></tr>
        @endforelse
    </tbody>
</table></div></div>
<div class="mt-6">{{ $payments->links() }}</div>
@endsection
