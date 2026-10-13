<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreBackupRequest;
use App\Http\Requests\Admin\UploadBackupRequest;
use App\Models\Backup;
use App\Services\Audit\AuditService;
use App\Services\Backup\BackupService;
use App\Services\Backup\BackupArchiveInspector;
use App\Services\Backup\BackupRestoreService;
use App\Services\Backup\RestoreLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;
use RuntimeException;

class BackupController extends Controller
{
    public function index(): View
    {
        $backups = Backup::query()->with('creator')->latest()->paginate(15);

        return view('admin.backups.index', compact('backups'));
    }

    public function store(Request $request, BackupService $service): RedirectResponse
    {
        $backup = $service->enqueue($request->user());

        if ($backup->status === 'failed') {
            return redirect()->route('admin.backups.index')->withErrors(['backup' => 'Backup gagal dibuat. Periksa status dan detailnya di daftar.']);
        }

        return redirect()->route('admin.backups.index')->with('success', $backup->status === 'completed'
            ? 'Backup berhasil dibuat dan diverifikasi.'
            : 'Backup masuk antrean. Statusnya akan berubah setelah pekerja antrean memprosesnya.');
    }

    public function download(Backup $backup, AuditService $audit): StreamedResponse
    {
        abort_unless($backup->status === 'completed' && is_string($backup->file_path) && preg_match('/^backups\/(?:ruangajar-\d{8}-\d{6}-[0-9a-f-]{36}|uploads\/[0-9a-f-]{36})\.zip$/', $backup->file_path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($backup->file_path), 404);
        abort_unless(is_string($backup->checksum_sha256) && hash_equals($backup->checksum_sha256, hash_file('sha256', $disk->path($backup->file_path))), 409, 'Integritas arsip backup tidak valid.');

        $audit->record('backup.downloaded', $backup, [], ['size' => $backup->size]);

        return $disk->download($backup->file_path, basename($backup->file_path), [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function restore(Backup $backup, BackupArchiveInspector $inspector): View
    {
        try {
            $details = $inspector->inspect($backup);
            $error = null;
        } catch (RuntimeException $exception) {
            $details = null;
            $error = $exception->getMessage();
        }

        $currentDriver = DB::connection()->getDriverName();
        $compatible = $details && ($details['driver'] === $currentDriver
            || (in_array($details['driver'], ['mysql', 'mariadb'], true) && in_array($currentDriver, ['mysql', 'mariadb'], true)));

        return view('admin.backups.restore', compact('backup', 'details', 'error', 'compatible', 'currentDriver'));
    }

    public function upload(UploadBackupRequest $request, BackupArchiveInspector $inspector): RedirectResponse
    {
        $file = $request->file('archive');
        $path = $file?->storeAs('backups/uploads', Str::uuid().'.zip', 'local');
        if (! is_string($path)) {
            return back()->withErrors(['archive' => 'Arsip tidak dapat disimpan. Periksa ruang penyimpanan hosting.']);
        }

        $disk = Storage::disk('local');
        $backup = Backup::query()->create([
            'type' => 'uploaded',
            'status' => 'completed',
            'file_path' => $path,
            'size' => $disk->size($path),
            'checksum_sha256' => hash_file('sha256', $disk->path($path)),
            'started_at' => now(),
            'completed_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        try {
            $inspector->inspect($backup);
        } catch (RuntimeException $exception) {
            $backup->delete();
            $disk->delete($path);

            return back()->withErrors(['archive' => $exception->getMessage()]);
        }

        return redirect()->route('admin.backups.restore', $backup)
            ->with('success', 'Arsip berhasil diunggah dan diperiksa. Data belum dipulihkan; periksa rinciannya lalu konfirmasi.');
    }

    public function executeRestore(
        RestoreBackupRequest $request,
        Backup $backup,
        BackupRestoreService $restorer,
        RestoreLock $lock,
    ): Response {
        ignore_user_abort(true);
        @set_time_limit(0);

        $safetyPath = null;
        try {
            $safety = $lock->run(fn () => $restorer->restore($backup, function (Backup $copy) use (&$safetyPath): void {
                $safetyPath = $copy->file_path;
            }));
        } catch (\Throwable $exception) {
            Log::error('Web backup restore failed', [
                'backup_id' => $backup->id,
                'exception' => $exception,
            ]);

            if ($safetyPath !== null) {
                return response()->view('admin.backups.restore-failed', compact('safetyPath'), 503);
            }

            return redirect()->route('admin.backups.restore', $backup)
                ->withErrors(['restore' => 'Pemulihan tidak dimulai karena pemeriksaan atau backup pengaman gagal. Data aktif tidak diganti.']);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Pemulihan selesai. Masuk kembali dan periksa data. Backup pengaman tersedia dengan ID #'.$safety->id.'.');
    }
}
