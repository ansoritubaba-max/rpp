<!DOCTYPE html>
<html lang="id">
@php $branding = app(\App\Services\Settings\SystemSettings::class); @endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $title ?? 'Dashboard' }} · {{ $branding->get('app_name') }}</title>
    <link rel="icon" href="{{ $branding->faviconUrl() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body x-data="{ sidebar: false }" class="min-h-screen overflow-x-hidden bg-[#f7f8fc]">
    <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_80%_0%,rgba(190,216,255,.45),transparent_34%),radial-gradient(circle_at_10%_100%,rgba(221,214,254,.4),transparent_30%)]"></div>

    <aside class="fixed inset-y-0 left-0 z-50 flex w-[270px] -translate-x-full flex-col border-r border-slate-200/70 bg-[#f1f5ff]/95 px-4 py-5 backdrop-blur-xl transition-transform duration-300 lg:translate-x-0" :class="sidebar && 'translate-x-0'">
        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('dashboard') }}" class="mb-8 flex items-center gap-3 px-2">
            <img src="{{ $branding->logoUrl() }}" alt="Logo {{ $branding->get('app_name') }}" class="h-11 w-11 rounded-2xl bg-white object-contain shadow-sm ring-1 ring-slate-200">
            <span><strong class="block text-lg tracking-tight text-ink-950">{{ $branding->get('app_name') }}</strong><small class="block text-[10px] font-extrabold uppercase tracking-[.2em] text-brand-600">Teacher Suite</small></span>
        </a>

        <nav class="flex-1 space-y-1 overflow-y-auto">
            @if(auth()->user()->isAdmin())
                <p class="mb-2 px-3 text-[10px] font-extrabold uppercase tracking-[.2em] text-slate-400">Ruang Kendali</p>
                <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'sidebar-link-active' : '' }}"><x-icon name="chart" /> Ringkasan Bisnis</a>
                <a href="{{ route('admin.teachers.index') }}" class="sidebar-link {{ request()->routeIs('admin.teachers.*', 'admin.subscriptions.*') ? 'sidebar-link-active' : '' }}"><x-icon name="users" /> Kelola Guru</a>
                <a href="{{ route('admin.payments.index') }}" class="sidebar-link {{ request()->routeIs('admin.payments.*') ? 'sidebar-link-active' : '' }}"><x-icon name="payment" /> Pembayaran</a>
                <a href="{{ route('admin.plans.index') }}" class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'sidebar-link-active' : '' }}"><x-icon name="package" /> Paket & Limit</a>
                <a href="{{ route('admin.academic.index') }}" class="sidebar-link {{ request()->routeIs('admin.academic.*') ? 'sidebar-link-active' : '' }}"><x-icon name="school" /> Data Akademik</a>
                <a href="{{ route('admin.templates.index') }}" class="sidebar-link {{ request()->routeIs('admin.templates.*') ? 'sidebar-link-active' : '' }}"><x-icon name="file" /> Template Dokumen</a>
                <a href="{{ route('admin.ai.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai.*') ? 'sidebar-link-active' : '' }}"><x-icon name="sparkles" /> AI & Model</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit-logs.*') ? 'sidebar-link-active' : '' }}"><x-icon name="shield" /> Audit Log</a>
                <a href="{{ route('admin.backups.index') }}" class="sidebar-link {{ request()->routeIs('admin.backups.*') ? 'sidebar-link-active' : '' }}"><x-icon name="shield" /> Backup</a>
                <a href="{{ route('admin.settings.edit') }}" class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'sidebar-link-active' : '' }}"><x-icon name="settings" /> Pengaturan Sistem</a>
            @else
                <p class="mb-2 px-3 text-[10px] font-extrabold uppercase tracking-[.2em] text-slate-400">Ruang Kerja</p>
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'sidebar-link-active' : '' }}"><x-icon name="home" /> Beranda</a>
                <a href="{{ route('create.index') }}" class="sidebar-link {{ request()->routeIs('create.*', 'generator.*', 'rpp.*') ? 'sidebar-link-active' : '' }}"><x-icon name="wand" /> Buat Perangkat <span class="ml-auto rounded-full bg-brand-100 px-2 py-0.5 text-[10px] text-brand-700">AI</span></a>
                <a href="{{ route('documents.index') }}" class="sidebar-link {{ request()->routeIs('documents.*') ? 'sidebar-link-active' : '' }}"><x-icon name="file" /> Dokumen Saya</a>
                <a href="{{ route('questions.index') }}" class="sidebar-link {{ request()->routeIs('questions.*', 'question-sets.*') ? 'sidebar-link-active' : '' }}"><x-icon name="package" /> Bank Soal</a>
                <a href="{{ route('schools.edit') }}" class="sidebar-link {{ request()->routeIs('schools.*') ? 'sidebar-link-active' : '' }}"><x-icon name="school" /> Profil Sekolah</a>
                <a href="{{ route('subscription.index') }}" class="sidebar-link {{ request()->routeIs('subscription.*') ? 'sidebar-link-active' : '' }}"><x-icon name="package" /> Paket Saya</a>
            @endif
            <a href="{{ route('account.sessions.index') }}" class="sidebar-link {{ request()->routeIs('account.sessions.*') ? 'sidebar-link-active' : '' }}"><x-icon name="shield" /> Perangkat Masuk</a>
            <a href="{{ route('notifications.index') }}" class="sidebar-link {{ request()->routeIs('notifications.*') ? 'sidebar-link-active' : '' }}"><x-icon name="bell" /> Notifikasi @php $sidebarUnread = auth()->user()->notifications()->whereNull('read_at')->count(); @endphp @if($sidebarUnread)<span class="ml-auto rounded-full bg-rose-100 px-2 py-0.5 text-[10px] text-rose-700">{{ $sidebarUnread }}</span>@endif</a>
        </nav>

        <div class="mt-4 rounded-2xl border border-white/80 bg-white/75 p-3 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-slate-800 to-slate-600 text-sm font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-800">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-slate-400 transition hover:text-rose-500" aria-label="Keluar"><x-icon name="logout" /></button></form>
            </div>
        </div>
    </aside>

    <div x-show="sidebar" x-cloak @click="sidebar=false" class="fixed inset-0 z-40 bg-slate-950/30 backdrop-blur-sm lg:hidden"></div>

    <div class="min-h-screen pb-24 lg:ml-[270px] lg:pb-0">
        <header class="glass sticky top-0 z-30 mx-3 mt-3 flex h-16 items-center justify-between rounded-2xl px-4 sm:mx-5 sm:px-6 lg:mx-7 lg:mt-4">
            <div class="flex items-center gap-3">
                <button @click="sidebar=true" class="grid h-10 w-10 place-items-center rounded-xl text-slate-600 hover:bg-slate-100 lg:hidden"><x-icon name="menu" /></button>
                <div><p class="text-xs font-semibold text-slate-400">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</p><p class="hidden text-sm font-bold text-slate-800 sm:block">Selamat datang, {{ str(auth()->user()->name)->before(' ')->toString() }}!</p></div>
            </div>
            <div class="flex items-center gap-2">
                @unless(auth()->user()->isAdmin())@php $hasActivePlan = auth()->user()->activeSubscription()->exists(); @endphp<a href="{{ route('subscription.index') }}" class="hidden rounded-full px-3 py-1.5 text-xs font-bold sm:inline-flex {{ $hasActivePlan ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">● {{ $hasActivePlan ? 'Paket aktif' : 'Pilih paket' }}</a>@endunless
                <a href="{{ route('notifications.index') }}" aria-label="Notifikasi{{ $sidebarUnread ? ': '.$sidebarUnread.' belum dibaca' : '' }}" class="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-brand-600"><x-icon name="bell" />@if($sidebarUnread)<span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-violet-500 ring-2 ring-white"></span>@endif</a>
            </div>
        </header>

        <main class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @if(session('success'))<div class="mb-5 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><span class="grid h-7 w-7 place-items-center rounded-full bg-emerald-500 text-white"><x-icon name="check" class="h-4 w-4" /></span>{{ session('success') }}</div>@endif
            @if(isset($slot)){{ $slot }}@endif
            @yield('content')
        </main>
    </div>

    @unless(auth()->user()->isAdmin())
    <nav class="glass fixed bottom-3 left-1/2 z-40 flex w-[calc(100%-24px)] max-w-md -translate-x-1/2 items-center justify-around rounded-2xl px-2 py-2 lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex min-w-16 flex-col items-center gap-1 rounded-xl px-2 py-1.5 text-[10px] font-bold {{ request()->routeIs('dashboard') ? 'text-brand-700' : 'text-slate-400' }}"><x-icon name="home" class="h-5 w-5" />Beranda</a>
        <a href="{{ route('create.index') }}" class="flex min-w-16 flex-col items-center gap-1 rounded-xl px-2 py-1.5 text-[10px] font-bold {{ request()->routeIs('create.*', 'generator.*', 'rpp.*') ? 'text-brand-700' : 'text-slate-400' }}"><x-icon name="wand" class="h-5 w-5" />Buat</a>
        <a href="{{ route('documents.index') }}" class="flex min-w-16 flex-col items-center gap-1 rounded-xl px-2 py-1.5 text-[10px] font-bold {{ request()->routeIs('documents.*') ? 'text-brand-700' : 'text-slate-400' }}"><x-icon name="file" class="h-5 w-5" />Dokumen</a>
        <a href="{{ route('schools.edit') }}" class="flex min-w-16 flex-col items-center gap-1 rounded-xl px-2 py-1.5 text-[10px] font-bold {{ request()->routeIs('schools.*') ? 'text-brand-700' : 'text-slate-400' }}"><x-icon name="school" class="h-5 w-5" />Akun</a>
    </nav>
    @endunless

    @if(! auth()->user()->isAdmin() && session()->pull('subject_help_notice', false))
        <div x-data="{ open: true }" x-show="open" x-cloak x-trap.noscroll="open" x-on:keydown.escape.window="open = false" x-init="$nextTick(() => $refs.dismiss.focus())" class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="subject-help-title" aria-describedby="subject-help-description">
            <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-sm" x-on:click="open = false" aria-hidden="true"></div>
            <div class="relative w-full max-w-lg rounded-[1.75rem] border border-white bg-white p-6 shadow-[0_30px_90px_-25px_rgba(15,23,42,.5)] sm:p-8">
                <span class="mb-5 grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-700"><x-icon name="school" /></span>
                <p class="eyebrow">Bantuan data akademik</p>
                <h2 id="subject-help-title" class="mt-2 text-2xl font-black tracking-tight text-ink-950">Mata pelajaran belum tersedia?</h2>
                <p id="subject-help-description" class="mt-3 text-sm leading-6 text-slate-600">Jika mata pelajaran yang Ibu/Bapak butuhkan belum ada, silakan hubungi admin. Data mapel, KD, CP, dan referensi kurikulum dikelola secara dinamis sesuai kebutuhan guru serta ditinjau mengacu pada ketentuan Kementerian Agama dan dinas pendidikan yang berlaku.</p>
                <p class="mt-3 text-sm font-bold text-slate-700">Admin: 0813 5785 3399</p>
                <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button x-ref="dismiss" type="button" x-on:click="open = false" class="btn-secondary justify-center">Mengerti</button>
                    <a href="https://wa.me/6281357853399?text=Halo%20Admin%20RuangAjar%2C%20saya%20membutuhkan%20mata%20pelajaran%20yang%20belum%20tersedia." target="_blank" rel="noopener noreferrer" class="btn-primary justify-center">Hubungi admin via WhatsApp</a>
                </div>
            </div>
        </div>
    @endif

    @livewireScripts
</body>
</html>
