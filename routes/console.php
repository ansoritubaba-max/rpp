<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Backup\BackupService;
use App\Services\Backup\BackupArchiveInspector;
use App\Services\Backup\BackupRestoreService;
use App\Models\Backup;
use App\Models\Subscription;
use App\Services\Notifications\NotificationService;
use App\Services\Maintenance\BackgroundCleanupService;
use App\Services\Maintenance\DailyAnalyticsAggregator;
use App\Services\Subscription\SubscriptionStatusSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\ExecutableFinder;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backup:create', function () {
    $backup = app(BackupService::class)->enqueue();
    $this->info("Backup #{$backup->id}: {$backup->status}");

    return $backup->status === 'failed' ? 1 : 0;
})->purpose('Create a private database and uploaded-files backup');

Artisan::command('backup:restore {backup : ID backup} {--execute : Jalankan pemulihan} {--confirm= : Ketik PULIHKAN-ID untuk konfirmasi}', function (BackupArchiveInspector $inspector, BackupRestoreService $restorer) {
    $backup = Backup::query()->findOrFail((int) $this->argument('backup'));
    try {
        $details = $inspector->inspect($backup);
        $this->info("Arsip #{$backup->id} valid: {$details['driver']}, {$details['storage_files']} berkas privat.");
        if (! $this->option('execute')) {
            $this->warn('Ini hanya pemeriksaan. Tidak ada data yang diubah.');

            return 0;
        }
        if (! app()->isDownForMaintenance()) {
            $this->error('Aktifkan mode pemeliharaan dengan php artisan down sebelum restore.');

            return 1;
        }
        if ($this->option('confirm') !== 'PULIHKAN-'.$backup->id) {
            $this->error('Konfirmasi tidak cocok. Gunakan --confirm=PULIHKAN-'.$backup->id);

            return 1;
        }
        $safety = $restorer->restore($backup, function (Backup $safety): void {
            $this->warn("Backup pengaman tersimpan di storage/app/private/{$safety->file_path}. Simpan jalur ini jika restore terhenti.");
        });
        $this->info("Restore selesai. Backup pengaman sebelum restore: #{$safety->id}.");
        $this->warn('Periksa aplikasi, lalu jalankan php artisan up secara terpisah.');

        return 0;
    } catch (\Throwable $error) {
        $this->error($error->getMessage());

        return 1;
    }
})->purpose('Verify a backup, or restore it during maintenance with explicit confirmation');

Schedule::command('backup:create')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('notifications:expiring-subscriptions', function (NotificationService $notifications) {
    Subscription::query()->with('user')
        ->where('status', 'active')
        ->where('expires_at', '>', now())
        ->where('expires_at', '<=', now()->addDays(7))
        ->chunkById(100, function ($subscriptions) use ($notifications): void {
            foreach ($subscriptions as $subscription) {
                if ($subscription->user) {
                    $notifications->expiringSubscription($subscription->user, $subscription);
                }
            }
        });
})->purpose('Notify teachers before subscriptions expire');

Schedule::command('notifications:expiring-subscriptions')->dailyAt('08:00')->withoutOverlapping();

Artisan::command('subscriptions:sync-status', function (SubscriptionStatusSynchronizer $sync) {
    $result = $sync->run();
    $this->info("Paket: {$result['expired']} kedaluwarsa, {$result['activated']} aktif, {$result['superseded']} digantikan.");
})->purpose('Synchronize pending and expired subscription statuses');

Schedule::command('subscriptions:sync-status')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('maintenance:cleanup', function (BackgroundCleanupService $cleanup) {
    $result = $cleanup->run();
    $this->info("Dibersihkan: {$result['otp']} OTP, {$result['files']} berkas sementara, {$result['cache']} cache AI; {$result['health']} cooldown direset.");
})->purpose('Clean expired OTP, temporary files, AI cache, and model cooldown');

Schedule::command('maintenance:cleanup')->hourly()->withoutOverlapping();

Artisan::command('analytics:aggregate-daily', function (DailyAnalyticsAggregator $aggregator) {
    $aggregator->run(now()->subDay());
    $this->info('Ringkasan analitik kemarin diperbarui.');
})->purpose('Aggregate daily document, AI, and payment activity');

Schedule::command('analytics:aggregate-daily')->dailyAt('00:10')->withoutOverlapping();

Schedule::command('queue:work database --queue=mail,pdf,maintenance,default --stop-when-empty --max-time=50 --sleep=1 --tries=3 --timeout=1800')
    ->everyMinute()->when(fn () => config('queue.worker_via_scheduler'))->withoutOverlapping(60)->runInBackground();

Artisan::command('deployment:check', function () {
    $finder = new ExecutableFinder;
    $binaryAvailable = static function (string $binary) use ($finder): bool {
        return str_contains($binary, DIRECTORY_SEPARATOR)
            ? is_file($binary) && is_executable($binary)
            : $finder->find($binary) !== null;
    };
    $smtp = config('mail.mailers.smtp');
    $from = (string) config('mail.from.address');
    $url = parse_url((string) config('app.url'));
    $host = is_array($url) ? ($url['host'] ?? '') : '';
    $googleRedirect = parse_url((string) config('services.google.redirect'));
    $googleCredentialsConfigured = filled(config('services.google.client_id'))
        && filled(config('services.google.client_secret'));
    $checks = [
        'Mode production' => app()->environment('production'),
        'Debug nonaktif' => config('app.debug') === false,
        'APP_KEY tersedia' => filled(config('app.key')),
        'URL HTTPS publik' => is_array($url) && ($url['scheme'] ?? '') === 'https'
            && ! in_array($host, ['', 'localhost', '127.0.0.1'], true),
        'Callback Google OAuth' => ! $googleCredentialsConfigured || (is_array($googleRedirect)
            && ($googleRedirect['scheme'] ?? '') === 'https'
            && ($googleRedirect['host'] ?? '') === $host
            && ($googleRedirect['path'] ?? '') === '/auth/google/callback'),
        'SMTP pengirim' => config('mail.default') === 'smtp'
            && (filled($smtp['url'] ?? null) || (filled($smtp['host'] ?? null)
                && filled($smtp['username'] ?? null) && filled($smtp['password'] ?? null))),
        'Alamat email pengirim' => filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && ! str_ends_with($from, '@example.com'),
        'Antrean database' => config('queue.default') === 'database'
            && (int) config('queue.connections.database.retry_after') > 1800,
        'Folder privat dapat ditulis' => is_dir(storage_path('app/private')) && is_writable(storage_path('app/private')),
        'Ekstensi ZIP' => extension_loaded('zip'),
        'Klien mysqldump' => $binaryAvailable((string) config('backup.mysql_dump_binary')),
        'Klien mysql' => $binaryAvailable((string) config('backup.mysql_client_binary')),
        'Midtrans Production' => (bool) config('services.midtrans.production')
            && filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key'))
            && filled(config('services.midtrans.merchant_id')),
    ];

    try {
        $checks['MySQL terhubung'] = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            && DB::selectOne('SELECT 1 AS healthy') !== null;
        $checks['Tabel antrean dan backup'] = Schema::hasTable('jobs') && Schema::hasTable('failed_jobs')
            && Schema::hasTable('backups');
    } catch (\Throwable) {
        $checks['MySQL terhubung'] = false;
        $checks['Tabel antrean dan backup'] = false;
    }

    foreach ($checks as $label => $passed) {
        $this->line(($passed ? '[OK]  ' : '[PERLU] ').$label);
    }
    $this->warn('Cron, pekerja antrean, pengiriman email, webhook, dan restore hanya dapat dipastikan dengan uji langsung di hosting.');

    return in_array(false, $checks, true) ? 1 : 0;
})->purpose('Check production configuration without printing secrets or changing data');

Artisan::command('deployment:test-email {recipient : Kotak masuk yang Anda kendalikan}', function () {
    $recipient = (string) $this->argument('recipient');
    if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
        $this->error('Alamat penerima tidak valid.');

        return 1;
    }
    if (config('mail.default') !== 'smtp') {
        $this->error('MAIL_MAILER belum smtp; email uji tidak dikirim.');

        return 1;
    }

    try {
        Mail::raw('Email uji konfigurasi RuangAjar. Jika pesan ini diterima, koneksi SMTP dari server berhasil.',
            fn ($message) => $message->to($recipient)->subject('Uji email RuangAjar'));
    } catch (\Throwable) {
        $this->error('SMTP gagal mengirim. Periksa host, port, keamanan TLS, kredensial, dan alamat pengirim.');

        return 1;
    }

    $this->info('Permintaan SMTP berhasil. Pastikan email benar-benar tiba di kotak masuk atau folder spam.');

    return 0;
})->purpose('Send one explicit SMTP smoke-test email to an operator-controlled inbox');
