<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDocumentSectionRequest;
use App\Http\Requests\DuplicateRppRequest;
use App\Http\Requests\RegenerateDocumentSectionRequest;
use App\Http\Requests\IndexDocumentsRequest;
use App\Models\Document;
use App\Models\Feature;
use App\Models\Grade;
use App\Models\Subject;
use App\Services\Documents\DocumentDuplicationService;
use App\Services\Documents\DocumentContinuationService;
use App\Services\Documents\DocumentSectionRegenerator;
use App\Services\Documents\DocumentPdfRenderer;
use App\Services\Monitoring\OperationalEventRecorder;
use App\Services\Subscription\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use DomainException;
use Throwable;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    public function index(IndexDocumentsRequest $request): View
    {
        $filters = $request->validated();
        $ownedDocuments = Document::query()->where('user_id', $request->user()->id);
        $documents = Document::query()
            ->with(['feature', 'subject', 'grade'])
            ->where('user_id', $request->user()->id)
            ->when(filled($filters['type'] ?? null), fn ($query) => $query->whereHas('feature', fn ($feature) => $feature->where('code', $filters['type'])))
            ->when(filled($filters['search'] ?? null), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', '%'.$filters['search'].'%')
                ->orWhere('topic', 'like', '%'.$filters['search'].'%')))
            ->when(filled($filters['subject_id'] ?? null), fn ($query) => $query->where('subject_id', $filters['subject_id']))
            ->when(filled($filters['grade_id'] ?? null), fn ($query) => $query->where('grade_id', $filters['grade_id']))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'filters' => $filters,
            'types' => Feature::query()->whereIn('id', (clone $ownedDocuments)->select('feature_id'))->orderBy('name')->get(['code', 'name']),
            'subjects' => Subject::query()->whereIn('id', (clone $ownedDocuments)->whereNotNull('subject_id')->select('subject_id'))->orderBy('name')->get(['id', 'name']),
            'grades' => Grade::query()->with('educationLevel')->whereIn('id', (clone $ownedDocuments)->whereNotNull('grade_id')->select('grade_id'))->orderBy('numeric_value')->get(['id', 'name', 'education_level_id']),
        ]);
    }

    public function show(Document $document, DocumentSectionRegenerator $regenerator, QuotaService $quotas, DocumentContinuationService $continuation): View
    {
        $this->authorize('view', $document);
        $document->load(['school', 'feature', 'curriculum', 'educationLevel', 'vocationalProgram', 'grade', 'subject', 'user', 'versions', 'sourceDocument.feature']);

        $regeneratableKeys = auth()->user()->can('update', $document) ? $regenerator->supportedKeys($document) : [];
        $quota = $quotas->summary(auth()->user())['features']->firstWhere('code', $document->feature->code);
        $nextCodes = $continuation->nextCodes($document);
        $latestExport = $document->exports()->first();

        return view('documents.show', compact('document', 'regeneratableKeys', 'quota', 'nextCodes', 'latestExport'));
    }

    public function duplicate(DuplicateRppRequest $request, Document $document, DocumentDuplicationService $duplication): RedirectResponse
    {
        $copy = $duplication->duplicateRpp($document, $request->validated());

        return redirect()->route('documents.show', $copy)->with('success', 'Salinan RPP dibuat tanpa menggunakan kuota AI.');
    }

    public function regenerate(RegenerateDocumentSectionRequest $request, Document $document, DocumentSectionRegenerator $regenerator, QuotaService $quotas): RedirectResponse
    {
        $section = $request->validated('section');
        try {
            $quotas->runRegeneration($request->user(), $document->feature->code, function () use ($document, $request, $section, $regenerator) {
                $locked = Document::query()->whereKey($document->id)->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
                $content = $regenerator->regenerate($locked, $section);
                $nextVersion = ((int) $locked->versions()->max('version_number')) + 1;
                $locked->update(['content_json' => $content, 'status' => 'edited']);
                $locked->versions()->create([
                    'version_number' => $nextVersion,
                    'content_json' => $content,
                    'created_by' => $request->user()->id,
                ]);
            });
        } catch (DomainException $exception) {
            return back()->withErrors(['regenerate' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            Log::error('Document section regeneration failed', ['document_id' => $document->id, 'section' => $section, 'exception' => $exception]);
            return back()->withErrors(['regenerate' => 'Bagian belum berhasil dibuat ulang. Silakan coba lagi.']);
        }

        return back()->with('success', 'Bagian berhasil dibuat ulang dan tersimpan sebagai versi baru.');
    }

    public function update(UpdateDocumentSectionRequest $request, Document $document): RedirectResponse
    {
        $validated = $request->validated();

        $content = $document->content_json;
        $lines = collect(preg_split('/\r\n|\r|\n/', $validated['content']))
            ->map(fn ($line) => trim(strip_tags($line)))
            ->filter()
            ->values()
            ->all();
        if (isset($content['sections']) && is_array($content['sections'])) {
            $sectionIndex = collect($content['sections'])->search(fn ($section) => ($section['key'] ?? null) === $validated['section']);
            abort_if($sectionIndex === false, 422, 'Bagian dokumen tidak ditemukan.');
            abort_unless(in_array($content['sections'][$sectionIndex]['type'] ?? null, ['text', 'list'], true), 422, 'Bagian ini tidak dapat diedit sebagai teks.');

            $content['sections'][$sectionIndex]['content'] = $content['sections'][$sectionIndex]['type'] === 'text'
                ? implode("\n", $lines)
                : $lines;
        } else {
            abort_unless(in_array($validated['section'], ['objectives', 'opening', 'main_activity', 'closing', 'reflection'], true), 422, 'Bagian dokumen tidak ditemukan.');
            $content[$validated['section']] = $validated['section'] === 'reflection'
                ? ['teacher' => implode("\n", $lines), 'student' => data_get($content, 'reflection.student')]
                : $lines;
        }

        $nextVersion = ((int) $document->versions()->max('version_number')) + 1;
        $document->update(['content_json' => $content, 'status' => 'edited']);
        $document->versions()->create([
            'version_number' => $nextVersion,
            'content_json' => $content,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Perubahan tersimpan sebagai versi baru.');
    }

    public function download(Document $document, DocumentPdfRenderer $renderer, OperationalEventRecorder $events): Response|RedirectResponse
    {
        return $this->renderPdf($document, $renderer, $events, false);
    }

    public function print(Document $document, DocumentPdfRenderer $renderer, OperationalEventRecorder $events): Response|RedirectResponse
    {
        return $this->renderPdf($document, $renderer, $events, true);
    }

    private function renderPdf(Document $document, DocumentPdfRenderer $renderer, OperationalEventRecorder $events, bool $inline): Response|RedirectResponse
    {
        $this->authorize('view', $document);
        if ($document->status === 'draft' && data_get($document->metadata_json, 'source') === 'ai-unavailable') {
            return back()->withErrors(['document' => 'Draf ini belum berisi hasil AI. Lanjutkan formulir dan coba buat kembali sebelum mencetak.']);
        }

        try {
            [$pdf, $filename] = $renderer->render($document);

            return $inline ? $pdf->stream($filename) : $pdf->download($filename);
        } catch (Throwable $error) {
            Log::error('PDF generation failed', ['document_id' => $document->id, 'user_id' => $document->user_id, 'exception' => $error]);
            $events->record('pdf_failed', $document->user_id, $document->id);

            return back()->withErrors(['pdf' => 'PDF belum berhasil dibuat. Silakan coba lagi beberapa saat.']);
        }
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Dokumen dipindahkan dari daftar Anda.');
    }
}
