<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDocumentPdfJob;
use App\Models\Document;
use App\Models\DocumentExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentExportController extends Controller
{
    public function store(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('view', $document);
        if ($document->status === 'draft' && data_get($document->metadata_json, 'source') === 'ai-unavailable') {
            return back()->withErrors(['pdf' => 'Draf ini belum berisi hasil AI.']);
        }

        $hash = hash('sha256', json_encode($document->content_json, JSON_THROW_ON_ERROR));
        $existing = $document->exports()->where('content_hash', $hash)
            ->where(function ($query) {
                $query->whereIn('status', ['queued', 'running'])
                    ->orWhere(fn ($ready) => $ready->where('status', 'completed')->where('expires_at', '>', now()));
            })->first();

        if (! $existing) {
            $export = $document->exports()->create([
                'user_id' => $request->user()->id, 'content_hash' => $hash, 'status' => 'queued',
            ]);
            GenerateDocumentPdfJob::dispatch($export->id)->afterCommit();
        }

        return back()->with('success', 'PDF sedang disiapkan di latar belakang. Segarkan halaman ini untuk melihat hasilnya.');
    }

    public function download(Request $request, DocumentExport $export): StreamedResponse
    {
        $document = $export->document;
        abort_unless($document && $request->user()->can('view', $document), 403);
        abort_unless($export->status === 'completed' && $export->expires_at?->isFuture(), 404);
        abort_unless(hash('sha256', json_encode($document->content_json, JSON_THROW_ON_ERROR)) === $export->content_hash, 409, 'Dokumen berubah. Siapkan PDF baru.');
        abort_unless(is_string($export->file_path) && preg_match('#^document-exports/\d+-[0-9a-f-]{36}\.pdf$#', $export->file_path), 404);
        abort_unless(Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download($export->file_path, $export->filename ?? 'dokumen.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
