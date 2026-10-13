<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Services\Settings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class DocumentPdfRenderer
{
    public function __construct(
        private readonly DocumentTemplateResolver $templates,
        private readonly DocumentPdfLayout $layout,
        private readonly SchoolLogoService $logos,
        private readonly SystemSettings $settings,
    ) {}

    public function render(Document $document): array
    {
        $document->load(['school', 'feature', 'curriculum', 'educationLevel', 'vocationalProgram', 'grade', 'subject', 'user']);
        $template = $this->templates->resolve($document);
        $systemSettings = $this->settings->all();
        $renderSections = $this->templates->sections($document, $template);
        $orientation = $this->layout->orientation($document, $template, $renderSections);
        $logoDataUris = [
            'left' => $document->school ? $this->logos->dataUri($document->school, 'left') : null,
            'right' => $document->school ? $this->logos->dataUri($document->school, 'right') : null,
        ];
        $filename = Str::of($document->title.'_'.$document->created_at->year)->ascii()->slug('_').'.pdf';
        $pdf = Pdf::loadView('documents.pdf', compact('document', 'template', 'renderSections', 'logoDataUris', 'systemSettings', 'orientation'))
            ->setPaper($template?->paper_size ?? $systemSettings['paper_size'], $orientation);

        return [$pdf, (string) $filename];
    }
}
