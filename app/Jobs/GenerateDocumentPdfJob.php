<?php

namespace App\Jobs;

use App\Models\DocumentExport;
use App\Services\Documents\DocumentPdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateDocumentPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $exportId)
    {
        $this->onQueue('pdf');
    }

    public function handle(DocumentPdfRenderer $renderer): void
    {
        $export = DocumentExport::query()->with('document')->findOrFail($this->exportId);
        if ($export->status !== 'queued') {
            return;
        }

        $document = $export->document;
        if (! $document || hash('sha256', json_encode($document->content_json, JSON_THROW_ON_ERROR)) !== $export->content_hash) {
            $export->update(['status' => 'stale', 'error_message' => 'Isi dokumen telah berubah. Siapkan PDF baru.']);

            return;
        }

        $export->update(['status' => 'running']);
        $path = 'document-exports/'.$export->id.'-'.Str::uuid().'.pdf';
        try {
            [$pdf, $filename] = $renderer->render($document);
            Storage::disk('local')->put($path, $pdf->output());
            $export->update([
                'status' => 'completed', 'file_path' => $path, 'filename' => $filename,
                'expires_at' => now()->addDay(),
            ]);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            $export->update(['status' => 'failed', 'error_message' => 'PDF belum berhasil dibuat. Silakan coba lagi.']);
            Log::error('Queued PDF generation failed', ['export_id' => $export->id, 'exception' => $error]);
        }
    }

    public function failed(Throwable $error): void
    {
        DocumentExport::query()->whereKey($this->exportId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed', 'error_message' => 'PDF belum berhasil dibuat. Silakan coba lagi.',
        ]);
        Log::error('Queued PDF job failed', ['export_id' => $this->exportId, 'exception' => $error]);
    }
}
