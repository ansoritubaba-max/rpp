@extends('layouts.app', ['title' => 'Beranda'])

@section('content')
<section class="mb-7 grid gap-5 xl:grid-cols-[1.45fr_.55fr]">
    <div class="relative overflow-hidden rounded-[1.7rem] bg-gradient-to-br from-[#17316d] via-[#1e4fb8] to-[#6946d7] p-6 text-white shadow-[0_24px_70px_-32px_rgba(30,64,175,.75)] sm:p-8">
        <div class="absolute -right-10 -top-20 h-64 w-64 rounded-full border-[40px] border-white/5"></div><div class="absolute bottom-0 right-1/4 h-28 w-28 rounded-full bg-white/5 blur-xl"></div>
        <div class="relative max-w-2xl"><span class="mb-5 inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-bold text-blue-100 backdrop-blur">✨ Ruang kerja hari ini</span><h1 class="text-3xl font-black tracking-tight sm:text-4xl">Apa yang ingin Ibu/Bapak siapkan?</h1><p class="mt-3 max-w-xl text-sm leading-6 text-blue-100 sm:text-base">Mulai dari konteks kelas. Kurikulum, struktur, dan format dokumen akan kami rapikan untuk Anda.</p><a href="{{ route('create.index') }}" class="mt-7 inline-flex min-h-12 items-center gap-2 rounded-xl bg-white px-5 font-extrabold text-brand-700 shadow-lg shadow-blue-950/20 transition hover:-translate-y-0.5">Buat perangkat baru <x-icon name="arrow-right" /></a></div>
    </div>

    <div class="surface p-5 sm:p-6">
        @if($quota['subscription'])
            <div class="flex items-start justify-between"><div><p class="eyebrow">{{ $isTrialSubscription ? 'Trial aktif' : 'Paket aktif' }}</p><h2 class="mt-2 text-xl font-black text-ink-950">{{ $quota['subscription']->plan->name }}</h2></div><span class="chip bg-emerald-50 text-emerald-700">● Aktif</span></div>
            <div class="mt-6 rounded-2xl bg-slate-50 p-4"><div class="flex items-center justify-between text-xs"><span class="font-semibold text-slate-500">Masa aktif</span><span class="font-extrabold text-slate-800">{{ (int) ceil(abs(now()->diffInDays($quota['subscription']->expires_at))) }} hari lagi</span></div><div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full w-4/5 rounded-full bg-gradient-to-r from-brand-500 to-violet-500"></div></div><p class="mt-3 text-xs text-slate-400">Berakhir {{ $quota['subscription']->expires_at->locale('id')->translatedFormat('d M Y') }}</p></div>
            <div class="mt-4 flex flex-wrap items-center gap-4"><a href="#quota" class="inline-flex items-center gap-1 text-xs font-extrabold text-brand-700">Lihat penggunaan <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a><a href="{{ route('subscription.index') }}#pilih-paket" class="inline-flex items-center gap-1 text-xs font-extrabold text-brand-700">{{ $isTrialSubscription ? 'Upgrade paket' : 'Perpanjang / upgrade' }} <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a></div>
        @else
            <p class="eyebrow">Paket Anda</p><h2 class="mt-2 text-xl font-black text-ink-950">{{ $latestSubscription && $latestSubscription->expires_at->isPast() ? ($isTrialSubscription ? 'Trial sudah berakhir' : 'Paket sudah berakhir') : 'Belum aktif' }}</h2><p class="mt-3 text-sm text-slate-500">{{ $latestSubscription && $latestSubscription->expires_at->isPast() ? 'Pilih paket untuk melanjutkan pembuatan dokumen. Dokumen lama tetap tersimpan.' : 'Aktifkan paket untuk membuat dokumen baru.' }}</p><a href="{{ route('subscription.index') }}#pilih-paket" class="btn-primary mt-5 w-full justify-center">Lihat paket & upgrade</a>
        @endif
    </div>
</section>

<section class="mb-7 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
    @foreach([['file','Dokumen',number_format($documentCount),'bg-brand-50 text-brand-600'],['wand','Fitur aktif',$featureCount,'bg-violet-50 text-violet-600'],['clock','Waktu hemat','± 6 jam','bg-amber-50 text-amber-600'],['check','Tersimpan','Otomatis','bg-emerald-50 text-emerald-600']] as [$icon,$label,$value,$style])
    <div class="surface flex items-center gap-3 p-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $style }}"><x-icon :name="$icon" /></span><div><p class="text-[11px] font-semibold text-slate-400 sm:text-xs">{{ $label }}</p><p class="mt-0.5 text-lg font-black text-ink-950">{{ $value }}</p></div></div>
    @endforeach
</section>

<section class="mb-8">
    <div class="mb-4 flex items-end justify-between"><div><p class="eyebrow">Akses cepat</p><h2 class="mt-1 text-2xl font-black tracking-tight text-ink-950">Buat perangkat</h2></div><a href="{{ route('create.index') }}" class="hidden text-sm font-bold text-brand-700 sm:block">Lihat semua</a></div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        @php $quickFeatures = [['rpp','RPP','Rencana pembelajaran','wand','from-blue-500 to-brand-700'],['modul_ajar','Modul Ajar','Modul lengkap','file','from-violet-500 to-purple-700'],['lkpd','LKPD','Lembar kegiatan','edit','from-emerald-500 to-teal-700'],['soal','Soal','Asesmen & kunci','check','from-amber-500 to-orange-600'],['atp','ATP','Alur tujuan','chart','from-rose-500 to-pink-700']]; @endphp
        @foreach($quickFeatures as [$code,$name,$copy,$icon,$gradient])
            <a href="{{ $code === 'rpp' ? route('rpp.create') : route('generator.create', $code) }}" class="surface group relative overflow-hidden p-4 transition duration-300 hover:-translate-y-1 hover:border-brand-200 sm:p-5"><span class="mb-5 grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br {{ $gradient }} text-white shadow-md"><x-icon :name="$icon" /></span><h3 class="font-extrabold text-ink-950">{{ $name }}</h3><p class="mt-1 text-xs text-slate-400">{{ $copy }}</p><span class="absolute right-3 top-3 rounded-full bg-emerald-50 px-2 py-1 text-[9px] font-extrabold text-emerald-700">AKTIF</span></a>
        @endforeach
    </div>
</section>

<section class="grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
    <div class="surface overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6"><div><h2 class="font-black text-ink-950">Dokumen terbaru</h2><p class="mt-0.5 text-xs text-slate-400">Lanjutkan pekerjaan terakhir Anda.</p></div><a href="{{ route('documents.index') }}" class="text-xs font-extrabold text-brand-700">Lihat semua</a></div>
        @forelse($recentDocuments as $document)
            <a href="{{ route('documents.show', $document) }}" class="flex items-center gap-4 border-b border-slate-100 px-5 py-4 transition last:border-0 hover:bg-brand-50/40 sm:px-6"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 font-black text-brand-600">{{ strtoupper(substr($document->feature->name, 0, 2)) }}</span><div class="min-w-0 flex-1"><h3 class="truncate text-sm font-extrabold text-slate-800">{{ $document->title }}</h3><p class="mt-1 truncate text-xs text-slate-400">{{ $document->subject?->name }} · {{ $document->grade?->name }} · {{ $document->updated_at->diffForHumans() }}</p></div><span class="chip hidden bg-emerald-50 text-emerald-700 sm:inline-flex">{{ ucfirst($document->status) }}</span><x-icon name="arrow-right" class="h-4 w-4 text-slate-300" /></a>
        @empty
            <div class="px-6 py-12 text-center"><span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="file" /></span><h3 class="mt-4 font-extrabold text-ink-950">Belum ada dokumen</h3><p class="mt-1 text-sm text-slate-400">Buat perangkat pertama untuk memulai.</p></div>
        @endforelse
    </div>

    <div id="quota" class="surface p-5 sm:p-6"><div class="mb-5 flex items-center justify-between"><div><h2 class="font-black text-ink-950">Penggunaan paket</h2><p class="mt-0.5 text-xs text-slate-400">Reset saat paket diperpanjang.</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><x-icon name="chart" /></span></div><div class="space-y-5">@foreach($quota['features']->take(4) as $item)<div><div class="mb-2 flex items-center justify-between text-xs"><span class="font-bold text-slate-700">{{ $item['name'] }}</span><span class="font-semibold text-slate-400">{{ $item['used'] }} / {{ $item['limit'] ?? '∞' }}</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $item['percentage'] > 80 ? 'bg-amber-500' : 'bg-brand-500' }}" style="width: {{ max(2, $item['percentage']) }}%"></div></div></div>@endforeach</div></div>
</section>
@endsection
