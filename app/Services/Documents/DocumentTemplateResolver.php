<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentTemplate;
use Illuminate\Support\Collection;

class DocumentTemplateResolver
{
    public function resolve(Document $document): ?DocumentTemplate
    {
        return DocumentTemplate::query()
            ->with('sections')
            ->where('feature_id', $document->feature_id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('curriculum_id')->orWhere('curriculum_id', $document->curriculum_id))
            ->where(fn ($query) => $query->whereNull('education_level_id')->orWhere('education_level_id', $document->education_level_id))
            ->get()
            ->sort(function (DocumentTemplate $first, DocumentTemplate $second) use ($document) {
                $score = fn (DocumentTemplate $template) =>
                    (int) ($template->curriculum_id !== null && $template->curriculum_id === $document->curriculum_id)
                    + (int) ($template->education_level_id !== null && $template->education_level_id === $document->education_level_id);

                return ($score($second) <=> $score($first)) ?: ($second->id <=> $first->id);
            })
            ->first();
    }

    public function sections(Document $document, ?DocumentTemplate $template = null): array
    {
        $source = $this->sourceSections($document)->keyBy('key');
        if (! $template || $template->sections->isEmpty()) {
            return $source->values()->all();
        }

        return $template->sections
            ->where('is_enabled', true)
            ->sortBy('sort_order')
            ->map(function ($setting) use ($source) {
                $section = $source->get($setting->section_key);
                if ($setting->source_type === 'static' && filled($setting->preset_content)) {
                    return [
                        'key' => $setting->section_key,
                        'title' => $setting->title,
                        'type' => 'text',
                        'content' => $setting->preset_content,
                    ];
                }

                if ($section) {
                    return array_replace($section, ['title' => $setting->title]);
                }

                if (filled($setting->preset_content)) {
                    return [
                        'key' => $setting->section_key,
                        'title' => $setting->title,
                        'type' => 'text',
                        'content' => $setting->preset_content,
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function sourceSections(Document $document): Collection
    {
        $content = $document->content_json ?? [];
        if ($document->feature?->code === 'rpp') {
            $content = DocumentContentFormatter::normalizeRpp($content);
        }

        if (isset($content['sections']) && is_array($content['sections'])) {
            return collect($content['sections'])->filter(fn ($section) => is_array($section) && filled($section['key'] ?? null));
        }

        return collect([
            ['key' => 'objectives', 'title' => 'A. Tujuan Pembelajaran', 'type' => 'list', 'content' => data_get($content, 'objectives', [])],
            ['key' => 'material', 'title' => 'B. Materi Pembelajaran', 'type' => 'table', 'headers' => ['Komponen', 'Uraian'], 'content' => [
                ['Topik', data_get($content, 'material.topic', $document->topic)],
                ['Subtopik', data_get($content, 'material.subtopic', $document->subtopic)],
                ['Sumber belajar', data_get($content, 'material.resources')],
            ]],
            ['key' => 'opening', 'title' => 'C. Kegiatan Pendahuluan', 'type' => 'list', 'content' => data_get($content, 'opening', [])],
            ['key' => 'main_activity', 'title' => 'D. Kegiatan Inti', 'type' => 'activity_plan', 'content' => DocumentContentFormatter::activityGroups(data_get($content, 'main_activity', []))],
            ['key' => 'closing', 'title' => 'E. Kegiatan Penutup', 'type' => 'list', 'content' => data_get($content, 'closing', [])],
            ['key' => 'assessment', 'title' => 'F. Asesmen', 'type' => 'table', 'headers' => ['Jenis', 'Teknik / Instrumen'], 'content' => collect(data_get($content, 'assessment', []))->map(fn ($item) => [$item['type'] ?? '', $item['method'] ?? ''])->all()],
            ['key' => 'differentiation', 'title' => 'G. Remedial dan Pengayaan', 'type' => 'list', 'content' => array_values(array_filter([
                filled(data_get($content, 'differentiation.support')) ? 'Dukungan: '.data_get($content, 'differentiation.support') : null,
                filled(data_get($content, 'differentiation.remedial')) ? 'Remedial: '.data_get($content, 'differentiation.remedial') : null,
                filled(data_get($content, 'differentiation.enrichment')) ? 'Pengayaan: '.data_get($content, 'differentiation.enrichment') : null,
            ]))],
            ['key' => 'reflection', 'title' => 'H. Refleksi', 'type' => 'list', 'content' => array_values(array_filter([
                filled(data_get($content, 'reflection.student')) ? 'Peserta didik: '.data_get($content, 'reflection.student') : null,
                filled(data_get($content, 'reflection.teacher')) ? 'Guru: '.data_get($content, 'reflection.teacher') : null,
            ]))],
        ]);
    }
}
