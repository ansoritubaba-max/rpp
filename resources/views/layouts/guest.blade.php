<!DOCTYPE html>
<html lang="id">
@php $branding = app(\App\Services\Settings\SystemSettings::class); @endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $title ?? $branding->get('app_name') }} · {{ $branding->get('app_name') }}</title>
    <link rel="icon" href="{{ $branding->faviconUrl() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f7fc]">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-brand-200/50 blur-3xl"></div>
        <div class="absolute -bottom-20 right-0 h-96 w-96 rounded-full bg-violet-200/40 blur-3xl"></div>
    </div>
    <main class="flex min-h-screen items-center justify-center p-5 sm:p-8">
        @yield('content')
    </main>
</body>
</html>
