@extends('layouts.app', ['title' => 'Profil Sekolah'])

@section('content')
<div class="mb-7">
    <p class="eyebrow">Identitas utama</p>
    <h1 class="mt-1 text-3xl font-black tracking-tight text-ink-950">Profil & kop sekolah</h1>
    <p class="mt-2 text-sm text-slate-500">Identitas dan logo ini otomatis masuk ke dokumen PDF yang Anda unduh.</p>
</div>

@if($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800"><p class="font-black">Periksa kembali data sekolah:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('schools.update') }}" enctype="multipart/form-data"
    x-data="{
        schoolName: @js(old('name', $school?->name)),
        foundationName: @js(old('foundation_name', $school?->foundation_name)),
        address: @js(old('address', $school?->address)),
        city: @js(old('city', $school?->city)),
        province: @js(old('province', $school?->province)),
        phone: @js(old('phone', $school?->phone)),
        email: @js(old('email', $school?->email)),
        website: @js(old('website', $school?->website)),
        leftUrl: @js($school?->logo_left ? route('schools.logo', 'left') : null),
        rightUrl: @js($school?->logo_right ? route('schools.logo', 'right') : null),
        leftPreview: @js($school?->logo_left ? route('schools.logo', 'left') : null),
        rightPreview: @js($school?->logo_right ? route('schools.logo', 'right') : null),
        removeLeft: @js((bool) old('remove_logo_left', false)),
        removeRight: @js((bool) old('remove_logo_right', false)),
        previewFile(side, event) {
            const key = side === 'left' ? 'leftPreview' : 'rightPreview';
            if (this[key]?.startsWith('blob:')) URL.revokeObjectURL(this[key]);
            this[key] = event.target.files[0] ? URL.createObjectURL(event.target.files[0]) : this[side === 'left' ? 'leftUrl' : 'rightUrl'];
            if (event.target.files[0]) this[side === 'left' ? 'removeLeft' : 'removeRight'] = false;
        }
    }" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
    @csrf @method('PUT')

    <div class="space-y-6">
        <section class="surface p-5 sm:p-7">
            <div class="mb-6 flex items-center gap-3 border-b border-slate-100 pb-5"><span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="school" /></span><div><h2 class="font-black text-ink-950">Data sekolah</h2><p class="text-xs text-slate-400">Lengkapi identitas resmi sekolah atau madrasah.</p></div></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="sm:col-span-2"><span class="label">Nama sekolah / madrasah</span><input name="name" x-model="schoolName" class="field" placeholder="Contoh: SD Negeri Harapan Bangsa" required></label>
                <label><span class="label">NPSN</span><input name="npsn" value="{{ old('npsn', $school?->npsn) }}" class="field" placeholder="Nomor Pokok Sekolah Nasional"></label>
                <label><span class="label">NSM <span class="font-normal text-slate-400">(madrasah)</span></span><input name="nsm" value="{{ old('nsm', $school?->nsm) }}" class="field" placeholder="Nomor Statistik Madrasah"></label>
                <label class="sm:col-span-2"><span class="label">Nama yayasan <span class="font-normal text-slate-400">(opsional)</span></span><input name="foundation_name" x-model="foundationName" class="field"></label>
                <label class="sm:col-span-2"><span class="label">Alamat lengkap</span><textarea name="address" x-model="address" class="field min-h-24" required></textarea></label>
                <label><span class="label">Desa / kelurahan</span><input name="village" value="{{ old('village', $school?->village) }}" class="field"></label>
                <label><span class="label">Kecamatan</span><input name="district" value="{{ old('district', $school?->district) }}" class="field"></label>
                <label><span class="label">Kabupaten / kota</span><input name="city" x-model="city" class="field"></label>
                <label><span class="label">Provinsi</span><input name="province" x-model="province" class="field"></label>
                <label><span class="label">Email sekolah</span><input type="email" name="email" x-model="email" class="field"></label>
                <label><span class="label">Telepon</span><input name="phone" x-model="phone" class="field"></label>
                <label><span class="label">Nama kepala sekolah</span><input name="principal_name" value="{{ old('principal_name', $school?->principal_name) }}" class="field"></label>
                <label><span class="label">NIP / identitas kepala sekolah</span><input name="principal_identifier" value="{{ old('principal_identifier', $school?->principal_identifier) }}" class="field"></label>
                <label class="sm:col-span-2"><span class="label">Website <span class="font-normal text-slate-400">(opsional)</span></span><input type="url" name="website" x-model="website" class="field" placeholder="https://sekolah.sch.id"></label>
            </div>
        </section>

        <section class="surface p-5 sm:p-7">
            <div class="mb-5 border-b border-slate-100 pb-5"><h2 class="font-black text-ink-950">Logo kop sekolah</h2><p class="mt-1 text-xs leading-5 text-slate-400">Gunakan PNG atau JPG, maksimal 2 MB per logo. Bentuk persegi memberi hasil paling rapi.</p></div>
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach(['left' => 'Logo kiri', 'right' => 'Logo kanan'] as $side => $label)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="mb-4 flex items-center gap-3"><span class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-300"><template x-if="{{ $side === 'left' ? '!removeLeft && leftPreview' : '!removeRight && rightPreview' }}"><img :src="{{ $side === 'left' ? 'leftPreview' : 'rightPreview' }}" alt="{{ $label }}" class="h-full w-full object-contain p-1"></template><template x-if="{{ $side === 'left' ? 'removeLeft || !leftPreview' : 'removeRight || !rightPreview' }}"><x-icon name="school" class="h-6 w-6" /></template></span><div><p class="text-sm font-black text-slate-800">{{ $label }}</p><p class="text-xs text-slate-400">{{ $side === 'left' ? 'Umumnya logo sekolah' : 'Opsional, misalnya yayasan' }}</p></div></div>
                        <label class="block"><span class="sr-only">Unggah {{ strtolower($label) }}</span><input type="file" name="logo_{{ $side }}" accept="image/png,image/jpeg,.png,.jpg,.jpeg" x-on:change="previewFile('{{ $side }}', $event)" class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-100 file:px-3 file:py-2 file:font-bold file:text-brand-700 hover:file:bg-brand-200"></label>
                        @if($school?->{'logo_'.$side})<label class="mt-4 flex items-center gap-2 text-xs font-semibold text-slate-500"><input type="checkbox" name="remove_logo_{{ $side }}" value="1" x-model="{{ $side === 'left' ? 'removeLeft' : 'removeRight' }}" class="rounded text-rose-600"> Hapus logo ini</label>@endif
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end"><button class="btn-primary"><x-icon name="check" /> Simpan profil & kop</button></div>
    </div>

    <aside class="space-y-5 xl:sticky xl:top-28 xl:self-start">
        <div class="surface overflow-hidden"><div class="border-b border-slate-100 px-5 py-4"><p class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Pratinjau kop langsung</p></div><div class="bg-slate-100 p-5"><div class="aspect-[1/1.414] bg-white p-5 shadow-sm"><div class="grid grid-cols-[42px_1fr_42px] items-center gap-1 border-b-4 border-double border-slate-800 pb-3 text-center"><div class="grid h-10 w-10 place-items-center"><template x-if="!removeLeft && leftPreview"><img :src="leftPreview" alt="Logo kiri" class="max-h-10 max-w-10 object-contain"></template></div><div class="min-w-0"><p class="text-[8px] font-bold uppercase" x-text="foundationName || ''"></p><h3 class="mt-1 break-words text-[11px] font-black uppercase" x-text="schoolName || 'Nama Sekolah'"></h3><p class="mt-1 break-words text-[6px] text-slate-500" x-text="[address, city, province].filter(Boolean).join(' · ') || 'Alamat sekolah akan tampil di sini'"></p><p class="mt-0.5 break-words text-[6px] text-slate-500" x-text="[phone, email, website].filter(Boolean).join(' · ')"></p></div><div class="grid h-10 w-10 place-items-center"><template x-if="!removeRight && rightPreview"><img :src="rightPreview" alt="Logo kanan" class="max-h-10 max-w-10 object-contain"></template></div></div><div class="mx-auto mt-6 h-2 w-1/2 rounded bg-slate-200"></div><div class="mt-6 space-y-2">@for($i=0;$i<7;$i++)<div class="h-1.5 rounded bg-slate-100 {{ $i % 3 === 0 ? 'w-full' : 'w-4/5' }}"></div>@endfor</div></div></div></div>
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-5"><span class="grid h-9 w-9 place-items-center rounded-xl bg-white text-violet-600 shadow-sm"><x-icon name="sparkles" class="h-5 w-5" /></span><h3 class="mt-4 text-sm font-extrabold text-violet-950">Digunakan otomatis</h3><p class="mt-2 text-xs leading-5 text-violet-700">Logo, identitas, kepala sekolah, dan lokasi tanda tangan akan masuk ke PDF tanpa diketik ulang.</p></div>
    </aside>
</form>
@endsection
