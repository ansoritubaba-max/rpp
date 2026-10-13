<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Feature;
use App\Services\Documents\DocumentPdfLayout;
use PHPUnit\Framework\TestCase;

class DocumentPdfLayoutTest extends TestCase
{
    public function test_wide_standard_table_uses_landscape_without_overriding_custom_template(): void
    {
        $document = new Document;
        $document->setRelation('feature', new Feature(['name' => 'Silabus']));
        $standard = new DocumentTemplate(['name' => 'Template Standar Silabus', 'orientation' => 'portrait']);
        $custom = new DocumentTemplate(['name' => 'Template Khusus', 'orientation' => 'portrait']);
        $wide = [['type' => 'table', 'headers' => ['A', 'B', 'C', 'D', 'E', 'F']]];
        $narrow = [['type' => 'table', 'headers' => ['A', 'B', 'C']]];

        $layout = new DocumentPdfLayout;

        $this->assertSame('landscape', $layout->orientation($document, $standard, $wide));
        $this->assertSame('portrait', $layout->orientation($document, $standard, $narrow));
        $this->assertSame('portrait', $layout->orientation($document, $custom, $wide));
    }
}
