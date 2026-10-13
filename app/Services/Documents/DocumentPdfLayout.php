<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentTemplate;

class DocumentPdfLayout
{
    public function orientation(Document $document, ?DocumentTemplate $template, array $sections): string
    {
        $orientation = $template?->orientation ?? 'portrait';
        if ($orientation === 'landscape') {
            return $orientation;
        }

        $usesStandardTemplate = $template === null
            || $template->name === 'Template Standar '.$document->feature->name;
        $hasWideTable = collect($sections)->contains(fn (array $section) =>
            ($section['type'] ?? null) === 'table' && count($section['headers'] ?? []) >= 6
        );

        return $usesStandardTemplate && $hasWideTable ? 'landscape' : $orientation;
    }
}
