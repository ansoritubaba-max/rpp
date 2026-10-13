<!DOCTYPE html>
<html lang="id">
@php
    $branding = app(\App\Services\Settings\SystemSettings::class);
    $appName = $branding->get('app_name');
    $canonicalUrl = route('home');
    $shareImage = $branding->logoUrl();
    $seoTitle = "Buat RPP AI Gratis untuk Guru | {$appName}";
    $seoDescription = "Buat RPP AI gratis di {$appName}. Susun Modul Ajar, LKPD, Silabus, ATP, Prota, Promes, soal, dan kisi-kisi untuk guru Indonesia.";
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $canonicalUrl.'#organization',
                'name' => $appName,
                'url' => $canonicalUrl,
                'logo' => $shareImage,
            ],
            [
                '@type' => 'WebSite',
                '@id' => $canonicalUrl.'#website',
                'name' => $appName,
                'url' => $canonicalUrl,
                'description' => $seoDescription,
                'inLanguage' => 'id-ID',
                'publisher' => ['@id' => $canonicalUrl.'#organization'],
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => $canonicalUrl.'#application',
                'name' => $appName,
                'url' => $canonicalUrl,
                'applicationCategory' => 'EducationalApplication',
                'operatingSystem' => 'Web',
                'description' => $seoDescription,
                'inLanguage' => 'id-ID',
                'audience' => [
                    '@type' => 'EducationalAudience',
                    'educationalRole' => 'teacher',
                ],
                'featureList' => [
                    'Generator RPP AI',
                    'Modul Ajar',
                    'LKPD',
                    'Silabus dan ATP',
                    'Prota dan Promes',
                    'Soal dan Kisi-Kisi',
                    'Ekspor PDF',
                ],
                'publisher' => ['@id' => $canonicalUrl.'#organization'],
            ],
        ],
    ];
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="buat RPP gratis, ruang ajar, ruang guru, guru cerdas, RPP AI, buat RPP AI, generator RPP AI, aplikasi RPP, Modul Ajar AI, perangkat ajar guru, LKPD AI, Silabus AI, Kurikulum Merdeka">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="{{ $appName }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="id-ID" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="{{ $appName }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:width" content="472">
    <meta property="og:image:height" content="472">
    <meta property="og:image:alt" content="Logo {{ $appName }}">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $shareImage }}">
    <meta name="twitter:image:alt" content="Logo {{ $appName }}">

    <link rel="icon" href="{{ $branding->faviconUrl() }}">
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-700">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-brand-700 focus:shadow-lg">Lewati ke konten utama</a>

    <div class="relative isolate overflow-hidden bg-[#f7f9ff]">
        <div aria-hidden="true" class="pointer-events-none absolute -right-40 -top-64 h-[42rem] w-[42rem] rounded-full bg-brand-100/80 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-64 -left-40 h-[34rem] w-[34rem] rounded-full bg-amber-100/70 blur-3xl"></div>

        <header class="relative z-10 border-b border-slate-200/70 bg-white/75 backdrop-blur-xl">
            <nav aria-label="Navigasi utama" class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 lg:px-8">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 rounded-lg focus-visible:ring-4 focus-visible:ring-brand-200">
                    <img src="{{ $branding->logoUrl() }}" alt="Logo {{ $appName }}" class="h-11 w-11 shrink-0 rounded-xl bg-white object-contain p-1 shadow-sm ring-1 ring-slate-200">
                    <span class="truncate text-lg font-black tracking-tight text-ink-950 sm:text-xl">{{ $appName }}</span>
                </a>
                <div class="hidden items-center gap-8 text-sm font-semibold text-slate-600 lg:flex">
                    <a href="#perangkat" class="transition hover:text-brand-700">Perangkat ajar</a>
                    <a href="#cara-kerja" class="transition hover:text-brand-700">Cara kerja</a>
                    <a href="#untuk-siapa" class="transition hover:text-brand-700">Untuk siapa</a>
                </div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary !min-h-10 !px-4 sm:!px-5">Buka dashboard <x-icon name="arrow-right" class="hidden h-4 w-4 sm:block" /></a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-2 py-2 text-sm font-bold text-slate-600 transition hover:text-brand-700 focus-visible:ring-4 focus-visible:ring-brand-200 sm:px-3">Masuk</a>
                        <a href="{{ route('register') }}" class="btn-primary !min-h-10 !px-4 sm:!px-5">Daftar <span class="hidden sm:inline">sekarang</span><x-icon name="arrow-right" class="hidden h-4 w-4 sm:block" /></a>
                    @endauth
                </div>
            </nav>
        </header>

        <main id="konten">
            <section class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-20 pt-16 lg:grid-cols-[1fr_1fr] lg:gap-16 lg:px-8 lg:pb-28 lg:pt-24">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white px-3.5 py-2 text-xs font-extrabold tracking-wide text-brand-700 shadow-sm">
                        <x-icon name="sparkles" class="h-4 w-4" /> Ruang kerja perangkat ajar untuk guru
                    </div>
                    <h1 class="mt-7 text-[2.75rem] font-black leading-[1.09] tracking-[-.055em] text-ink-950 sm:text-6xl lg:text-[4.15rem]">
                        Buat RPP AI lebih cepat.<br><span class="text-brand-600">Ruang ajar untuk guru cerdas.</span>
                    </h1>
                    <p class="mt-6 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">
                        Mulai buat RPP gratis selama masa uji coba, susun draf dengan bantuan AI, lalu tinjau dan sesuaikan sebelum digunakan. RPP, Modul Ajar, LKPD, Silabus, hingga asesmen berada dalam satu ruang kerja guru.
                    </p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-primary !min-h-14 !px-7 !text-base">Buka ruang kerja <x-icon name="arrow-right" class="h-5 w-5" /></a>
                        @else
                            <a href="{{ route('register') }}" class="btn-primary !min-h-14 !px-7 !text-base">Mulai buat RPP gratis <x-icon name="arrow-right" class="h-5 w-5" /></a>
                        @endauth
                        <a href="#cara-kerja" class="btn-secondary !min-h-14 !px-7 !text-base">Lihat cara kerjanya</a>
                    </div>
                    <p class="mt-4 text-sm text-slate-500">AI membantu menyiapkan draf; keputusan akhir tetap di tangan guru.</p>
                    <div class="mt-9 flex flex-wrap gap-x-6 gap-y-3 border-t border-slate-200 pt-6 text-sm font-semibold text-slate-600">
                        <span class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-emerald-600" /> Beragam jenis dokumen</span>
                        <span class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-emerald-600" /> Bisa ditinjau dan disunting</span>
                        <span class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-emerald-600" /> Ekspor PDF</span>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-[35rem]">
                    <div aria-hidden="true" class="absolute -inset-4 rounded-[2.25rem] bg-gradient-to-br from-brand-200/80 via-white to-amber-100/80 blur-xl"></div>
                    <div class="relative rounded-[1.75rem] border border-white bg-white p-3 shadow-[0_28px_90px_-35px_rgba(26,62,137,.38)] sm:p-4">
                        <div class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-slate-50">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-6">
                                <div class="flex items-center gap-2 text-xs font-bold text-slate-600"><span class="grid h-7 w-7 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-icon name="file" class="h-4 w-4" /></span> Contoh pratinjau dokumen</div>
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800">Draf</span>
                            </div>
                            <div class="p-4 sm:p-7">
                                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                                    <div class="border-b border-slate-200 pb-5 text-center">
                                        <p class="text-[10px] font-bold uppercase tracking-[.22em] text-brand-600">Rencana Pelaksanaan Pembelajaran</p>
                                        <h2 class="mt-2 text-lg font-black tracking-tight text-ink-950 sm:text-xl">Pecahan dalam Kehidupan Sehari-hari</h2>
                                        <p class="mt-1 text-xs text-slate-500">Matematika · Kelas VI · Contoh isi</p>
                                    </div>
                                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-lg bg-brand-50 p-3"><p class="text-[10px] font-extrabold uppercase tracking-wider text-brand-700">Tujuan pembelajaran</p><p class="mt-1.5 text-xs leading-5 text-slate-700">Peserta didik membandingkan pecahan melalui situasi yang dekat dengan keseharian.</p></div>
                                        <div class="rounded-lg bg-amber-50 p-3"><p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">Aktivitas inti</p><p class="mt-1.5 text-xs leading-5 text-slate-700">Diskusi kelompok menggunakan gambar potongan kue dan garis bilangan.</p></div>
                                    </div>
                                    <div class="mt-5 space-y-3">
                                        <div class="flex items-start gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-slate-100 text-[11px] font-black text-slate-600">1</span><div><p class="text-xs font-bold text-slate-800">Pendahuluan</p><p class="mt-0.5 text-xs leading-5 text-slate-500">Menghubungkan materi dengan pengalaman peserta didik.</p></div></div>
                                        <div class="flex items-start gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-slate-100 text-[11px] font-black text-slate-600">2</span><div><p class="text-xs font-bold text-slate-800">Kegiatan pembelajaran</p><p class="mt-0.5 text-xs leading-5 text-slate-500">Eksplorasi, diskusi, dan latihan bertahap.</p></div></div>
                                        <div class="flex items-start gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-slate-100 text-[11px] font-black text-slate-600">3</span><div><p class="text-xs font-bold text-slate-800">Asesmen</p><p class="mt-0.5 text-xs leading-5 text-slate-500">Cek pemahaman melalui tugas singkat dan refleksi.</p></div></div>
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center justify-between gap-3 text-xs text-slate-500"><span>Contoh ilustrasi, bukan hasil dari akun pengguna</span><span class="inline-flex shrink-0 items-center gap-1.5 font-bold text-brand-700"><x-icon name="edit" class="h-4 w-4" /> Dapat disunting</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="perangkat" class="scroll-mt-24 bg-white px-5 py-20 lg:px-8 lg:py-24">
                <div class="mx-auto max-w-7xl">
                    <div class="max-w-3xl">
                        <p class="eyebrow">Satu ruang kerja, banyak kebutuhan</p>
                        <h2 class="mt-3 text-3xl font-black leading-tight tracking-tight text-ink-950 sm:text-4xl">Buat RPP AI, Modul Ajar, dan dokumen guru dalam satu ruang.</h2>
                        <p class="mt-4 text-base leading-7 text-slate-600">Pilih jenis perangkat ajar, isi konteks pembelajaran, lalu simpan hasilnya agar mudah disunting dan dilanjutkan kembali.</p>
                    </div>
                    <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach([
                            ['RPP & Modul Ajar', 'Rancang tujuan, kegiatan, asesmen, dan tindak lanjut pembelajaran.', 'file', 'bg-brand-50 text-brand-700'],
                            ['Silabus & ATP', 'Petakan alur materi dan capaian untuk rencana pembelajaran yang lebih jelas.', 'calendar', 'bg-violet-50 text-violet-700'],
                            ['LKPD', 'Siapkan petunjuk, aktivitas, dan lembar hasil untuk peserta didik.', 'edit', 'bg-amber-50 text-amber-700'],
                            ['Soal & Kisi-Kisi', 'Susun butir soal, kunci, serta rancangan penilaiannya.', 'grid', 'bg-emerald-50 text-emerald-700'],
                            ['Prota & Promes', 'Atur gambaran program tahunan dan semester dalam satu tempat.', 'clock', 'bg-sky-50 text-sky-700'],
                            ['Dokumen Tersimpan', 'Kembali ke draf, tinjau isi, sunting, dan ekspor PDF saat siap.', 'download', 'bg-rose-50 text-rose-700'],
                        ] as [$title, $description, $icon, $iconStyle])
                            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_12px_40px_-30px_rgba(15,23,42,.35)] transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-[0_22px_50px_-30px_rgba(30,92,232,.3)]">
                                <span class="grid h-12 w-12 place-items-center rounded-xl {{ $iconStyle }}"><x-icon :name="$icon" class="h-6 w-6" /></span>
                                <h3 class="mt-5 text-lg font-extrabold text-ink-950">{{ $title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="cara-kerja" class="scroll-mt-24 bg-slate-50 px-5 py-20 lg:px-8 lg:py-24">
                <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-start lg:gap-16">
                    <div>
                        <p class="eyebrow">Cara kerja</p>
                        <h2 class="mt-3 text-3xl font-black leading-tight tracking-tight text-ink-950 sm:text-4xl">Dari ide pembelajaran menjadi draf yang bisa Anda kendalikan.</h2>
                        <p class="mt-5 text-base leading-7 text-slate-600">Tidak perlu menyusun instruksi AI dari nol. Guru memberi konteks, sistem membantu merangkai draf, lalu guru memastikan isinya sesuai kebutuhan kelas.</p>
                        @guest
                            <a href="{{ route('register') }}" class="mt-7 inline-flex items-center gap-2 text-sm font-extrabold text-brand-700 hover:text-brand-800">Mulai buat akun <x-icon name="arrow-right" class="h-4 w-4" /></a>
                        @endguest
                    </div>
                    <ol class="grid gap-4 sm:grid-cols-3">
                        @foreach([
                            ['01', 'Isi kebutuhan kelas', 'Tentukan jenis dokumen, jenjang, mata pelajaran, kelas, dan materi.'],
                            ['02', 'Dapatkan draf', 'AI membantu menyusun isi berdasarkan informasi yang Anda berikan.'],
                            ['03', 'Tinjau dan gunakan', 'Periksa, sunting, simpan, lalu unduh PDF ketika hasilnya sudah sesuai.'],
                        ] as [$number, $title, $description])
                            <li class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <span class="text-sm font-black tracking-widest text-brand-600">{{ $number }}</span>
                                <h3 class="mt-5 text-lg font-extrabold text-ink-950">{{ $title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section id="untuk-siapa" class="scroll-mt-24 bg-white px-5 py-20 lg:px-8 lg:py-24">
                <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1fr_1fr] lg:items-center lg:gap-16">
                    <div class="rounded-[1.75rem] border border-brand-100 bg-[#f3f7ff] p-6 sm:p-8">
                        <div class="flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-white text-brand-700 shadow-sm"><x-icon name="school" class="h-6 w-6" /></span><span class="text-sm font-extrabold text-ink-950">Dirancang untuk konteks sekolah Indonesia</span></div>
                        <div class="mt-7 grid grid-cols-2 gap-3 text-sm font-bold text-slate-700 sm:grid-cols-4">
                            @foreach(['SD / MI', 'SMP / MTs', 'SMA / MA', 'SMK'] as $level)
                                <span class="rounded-xl border border-white bg-white px-3 py-4 text-center shadow-sm">{{ $level }}</span>
                            @endforeach
                        </div>
                        <p class="mt-5 text-sm leading-6 text-slate-600">Pilihan jenjang, mata pelajaran, dan konsentrasi keahlian SMK tersedia sesuai data yang dikelola di sistem.</p>
                    </div>
                    <div>
                        <p class="eyebrow">Untuk guru yang mengutamakan ketepatan</p>
                        <h2 class="mt-3 text-3xl font-black leading-tight tracking-tight text-ink-950 sm:text-4xl">Bantuan AI yang tetap memberi ruang untuk pertimbangan guru.</h2>
                        <p class="mt-5 text-base leading-7 text-slate-600">Setiap kelas berbeda. Karena itu hasil generasi adalah titik awal, bukan pengganti penilaian profesional Anda. Tinjau tujuan, materi, bahasa, dan asesmen sebelum dibagikan atau dicetak.</p>
                        <div class="mt-6 space-y-3 text-sm font-semibold text-slate-700">
                            <p class="flex items-start gap-3"><x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" /> Sesuaikan dokumen dengan kondisi peserta didik.</p>
                            <p class="flex items-start gap-3"><x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" /> Lanjutkan draf tanpa harus memulai dari awal.</p>
                            <p class="flex items-start gap-3"><x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" /> Simpan hasil untuk kebutuhan pembelajaran berikutnya.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="px-5 pb-20 lg:px-8 lg:pb-24">
                <div class="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-ink-950 px-6 py-14 text-center text-white sm:px-12 sm:py-16">
                    <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-28 h-80 w-80 rounded-full bg-brand-500/30 blur-3xl"></div>
                    <div aria-hidden="true" class="pointer-events-none absolute -bottom-28 -left-20 h-80 w-80 rounded-full bg-violet-500/25 blur-3xl"></div>
                    <div class="relative">
                        <p class="text-xs font-extrabold uppercase tracking-[.22em] text-brand-200">Mulai dari kebutuhan kelas Anda</p>
                        <h2 class="mx-auto mt-4 max-w-3xl text-3xl font-black leading-tight tracking-tight sm:text-5xl">Siapkan perangkat ajar berikutnya dengan lebih percaya diri.</h2>
                        <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-300">Buat akun untuk mulai menyusun draf, meninjau hasil, dan menyimpan dokumen pembelajaran dalam satu tempat.</p>
                        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-white px-7 text-sm font-extrabold text-brand-700 transition hover:bg-brand-50 focus-visible:ring-4 focus-visible:ring-white/40">Buka dashboard <x-icon name="arrow-right" class="h-4 w-4" /></a>
                            @else
                                <a href="{{ route('register') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-white px-7 text-sm font-extrabold text-brand-700 transition hover:bg-brand-50 focus-visible:ring-4 focus-visible:ring-white/40">Daftar ke {{ $appName }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
                                <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/30 px-7 text-sm font-bold text-white transition hover:bg-white/10 focus-visible:ring-4 focus-visible:ring-white/40">Sudah punya akun? Masuk</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

        <footer class="border-t border-slate-200 bg-slate-50 px-5 py-9 lg:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3"><img src="{{ $branding->logoUrl() }}" alt="" class="h-9 w-9 rounded-lg bg-white object-contain p-1 ring-1 ring-slate-200"><div><p class="font-extrabold text-ink-950">{{ $appName }}</p><p class="text-xs text-slate-500">Ruang kerja perangkat pembelajaran guru.</p></div></div>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-slate-600"><a href="#perangkat" class="hover:text-brand-700">Perangkat ajar</a><a href="#cara-kerja" class="hover:text-brand-700">Cara kerja</a><a href="{{ route('login') }}" class="hover:text-brand-700">Masuk</a></div>
            </div>
            <p class="mx-auto mt-8 max-w-7xl border-t border-slate-200 pt-6 text-xs text-slate-500">© {{ date('Y') }} {{ $appName }}.</p>
        </footer>
</body>
</html>
