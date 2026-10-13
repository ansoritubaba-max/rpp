@extends('layouts.app', ['title' => 'Catat Pembayaran'])

@section('content')
<div class="mx-auto max-w-3xl"><a href="{{ route('admin.payments.index') }}" class="mb-4 inline-flex items-center gap-1 text-xs font-bold text-slate-400"><x-icon name="arrow-left" class="h-4 w-4" /> Semua pembayaran</a><div class="mb-6"><p class="eyebrow">Transaksi manual</p><h1 class="mt-1 text-3xl font-black text-ink-950">Catat pembayaran</h1><p class="mt-2 text-sm text-slate-500">Jika langsung ditandai lunas, paket guru akan aktif setelah data tersimpan.</p></div>
<form method="POST" action="{{ route('admin.payments.store') }}" enctype="multipart/form-data" class="surface overflow-hidden">@csrf
    <div class="grid gap-5 p-6 sm:p-8 md:grid-cols-2">
        <label><span class="label">Guru</span><select name="user_id" class="field" required><option value="">Pilih guru</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach</select>@error('user_id')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label><span class="label">Paket</span><select name="plan_id" class="field" required><option value="">Pilih paket</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }} · Rp {{ number_format($plan->price, 0, ',', '.') }}</option>@endforeach</select>@error('plan_id')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label><span class="label">Nominal</span><input type="number" name="amount" value="{{ old('amount') }}" min="0" class="field" required></label>
        <label><span class="label">Metode pembayaran</span><select name="payment_method" class="field"><option>Transfer Bank</option><option>Tunai</option><option>QRIS Manual</option><option>Lainnya</option></select></label>
        <label><span class="label">Tanggal pembayaran</span><input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="field" required></label>
        <label><span class="label">Status</span><select name="status" class="field"><option value="pending">Pending</option><option value="paid">Lunas & aktifkan paket</option><option value="cancelled">Dibatalkan</option></select></label>
        <label class="md:col-span-2"><span class="label">Bukti pembayaran <span class="font-normal text-slate-400">(JPG, PNG, atau PDF; maks. 4 MB)</span></span><input type="file" name="proof_file" accept="image/jpeg,image/png,application/pdf" class="field py-2.5">@error('proof_file')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label class="md:col-span-2"><span class="label">Catatan</span><textarea name="notes" class="field min-h-24">{{ old('notes') }}</textarea></label>
    </div><div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4"><a href="{{ route('admin.payments.index') }}" class="btn-secondary">Batal</a><button class="btn-primary">Simpan pembayaran</button></div>
</form></div>
@endsection
