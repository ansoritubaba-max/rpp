<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Services\AI\AiManager;
use DomainException;

class DocumentSectionRegenerator
{
    public function __construct(private readonly AiManager $aiManager) {}

    public function supportedKeys(Document $document): array
    {
        if ($document->feature->code === 'rpp') {
            return ['objectives', 'opening', 'main_activity', 'closing', 'assessment'];
        }

        return collect($document->content_json['sections'] ?? [])
            ->filter(fn ($section) => in_array($section['type'] ?? null, ['text', 'list'], true))
            ->pluck('key')->all();
    }

    public function regenerate(Document $document, string $section): array
    {
        $document->loadMissing(['feature', 'curriculum', 'educationLevel', 'grade', 'subject']);
        if (! in_array($section, $this->supportedKeys($document), true)) {
            throw new DomainException('Bagian ini tidak dapat dibuat ulang.');
        }

        $content = $document->content_json;
        $sectionIndex = null;
        $type = $section === 'assessment' ? 'assessment' : 'list';
        $current = $content[$section] ?? [];
        if ($document->feature->code !== 'rpp') {
            $sectionIndex = collect($content['sections'])->search(fn ($item) => ($item['key'] ?? null) === $section);
            $type = $content['sections'][$sectionIndex]['type'];
            $current = $content['sections'][$sectionIndex]['content'];
        }

        $result = $this->aiManager->generate($document->feature->code, 'regenerate_'.$section, [
            'section' => $section,
            'output_type' => $type,
            'curriculum' => $document->curriculum?->name,
            'level' => $document->educationLevel?->name,
            'grade' => $document->grade?->name,
            'subject' => $document->subject?->name,
            'topic' => $document->topic,
            'subtopic' => $document->subtopic,
            'semester' => $document->semester,
            'current_section' => $current,
            'instruction' => 'Tulis ulang hanya bagian ini sebagai JSON dengan key content. Pertahankan konteks pembelajaran, gunakan bahasa Indonesia.',
        ], ['content']);

        if ($result === null) {
            throw new DomainException('Model AI untuk fitur ini belum tersedia. Dokumen dan kuota tidak berubah.');
        }

        $replacement = $this->validateContent($result['content'] ?? null, $type);
        if ($sectionIndex === null) {
            $content[$section] = $replacement;
        } else {
            $content['sections'][$sectionIndex]['content'] = $replacement;
        }

        return $content;
    }

    private function validateContent(mixed $value, string $type): array|string
    {
        if ($type === 'text') {
            $clean = is_string($value) ? trim(strip_tags($value)) : '';
            if ($clean === '' || mb_strlen($clean) > 20000) {
                throw new DomainException('Respons AI tidak berisi teks yang valid.');
            }
            return $clean;
        }

        if (! is_array($value) || ! array_is_list($value) || count($value) < 1 || count($value) > 20) {
            throw new DomainException('Respons AI tidak berisi daftar yang valid.');
        }

        $items = [];
        foreach ($value as $item) {
            if ($type === 'assessment') {
                $assessmentType = is_array($item) && is_string($item['type'] ?? null) ? trim(strip_tags($item['type'])) : '';
                $method = is_array($item) && is_string($item['method'] ?? null) ? trim(strip_tags($item['method'])) : '';
                if ($assessmentType === '' || $method === '' || mb_strlen($assessmentType) > 100 || mb_strlen($method) > 1000) {
                    throw new DomainException('Respons AI untuk asesmen tidak valid.');
                }
                $items[] = ['type' => $assessmentType, 'method' => $method];
            } else {
                $clean = is_string($item) ? trim(strip_tags($item)) : '';
                if ($clean === '' || mb_strlen($clean) > 2000) {
                    throw new DomainException('Respons AI berisi butir yang tidak valid.');
                }
                $items[] = $clean;
            }
        }

        return $items;
    }
}
