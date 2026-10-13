<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TemplateSectionRequest;
use App\Models\DocumentTemplate;
use App\Models\TemplateSection;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;

class TemplateSectionController extends Controller
{
    public function store(TemplateSectionRequest $request, DocumentTemplate $template, AuditService $auditService): RedirectResponse
    {
        $section = $template->sections()->create($request->validated());
        $auditService->record('template_section.created', $section, [], $this->snapshot($section));

        return back()->with('success', 'Bagian template berhasil ditambahkan.');
    }

    public function update(TemplateSectionRequest $request, DocumentTemplate $template, TemplateSection $section, AuditService $auditService): RedirectResponse
    {
        abort_unless($section->template_id === $template->id, 404);
        $before = $this->snapshot($section);
        $section->update($request->validated());
        $auditService->record('template_section.updated', $section, $before, $this->snapshot($section->fresh()));

        return back()->with('success', 'Bagian template berhasil diperbarui.');
    }

    private function snapshot(TemplateSection $section): array
    {
        return $section->only(['template_id', 'section_key', 'title', 'sort_order', 'source_type', 'preset_content', 'ai_task_key', 'is_required', 'is_enabled']);
    }
}
