<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentTemplateRequest;
use App\Models\Curriculum;
use App\Models\DocumentTemplate;
use App\Models\EducationLevel;
use App\Models\Feature;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.templates.index', [
            'templates' => DocumentTemplate::query()
                ->with(['feature', 'curriculum', 'educationLevel'])
                ->withCount(['sections', 'sections as enabled_sections_count' => fn ($query) => $query->where('is_enabled', true)])
                ->when(request('feature'), fn ($query, $feature) => $query->where('feature_id', $feature))
                ->when(request()->filled('status'), fn ($query) => $query->where('is_active', request()->boolean('status')))
                ->orderByDesc('is_active')->latest()->paginate(12)->withQueryString(),
            'features' => Feature::query()->where('code', '!=', 'bank_soal')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new DocumentTemplate);
    }

    public function store(DocumentTemplateRequest $request, AuditService $auditService): RedirectResponse
    {
        $template = DB::transaction(function () use ($request) {
            $values = $request->validated();
            $this->deactivateDuplicate($values);

            return DocumentTemplate::query()->create($values);
        });
        $auditService->record('document_template.created', $template, [], $this->snapshot($template));

        return redirect()->route('admin.templates.edit', $template)->with('success', 'Template dibuat. Sekarang atur susunan bagiannya.');
    }

    public function edit(DocumentTemplate $template): View
    {
        $template->load(['sections', 'feature', 'curriculum', 'educationLevel']);

        return $this->form($template);
    }

    public function update(DocumentTemplateRequest $request, DocumentTemplate $template, AuditService $auditService): RedirectResponse
    {
        $before = $this->snapshot($template);
        DB::transaction(function () use ($request, $template) {
            $values = $request->validated();
            $this->deactivateDuplicate($values, $template);
            $template->update($values);
        });
        $auditService->record('document_template.updated', $template, $before, $this->snapshot($template->fresh()));

        return back()->with('success', 'Pengaturan template berhasil disimpan.');
    }

    private function form(DocumentTemplate $template): View
    {
        return view('admin.templates.form', [
            'template' => $template,
            'features' => Feature::query()->where('code', '!=', 'bank_soal')->orderBy('name')->get(),
            'curriculums' => Curriculum::query()->where('is_active', true)->orderBy('name')->get(),
            'levels' => EducationLevel::query()->orderBy('sort_order')->get(),
        ]);
    }

    private function deactivateDuplicate(array $values, ?DocumentTemplate $except = null): void
    {
        if (! $values['is_active']) {
            return;
        }

        DocumentTemplate::query()
            ->where('feature_id', $values['feature_id'])
            ->where('curriculum_id', $values['curriculum_id'])
            ->where('education_level_id', $values['education_level_id'])
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->update(['is_active' => false]);
    }

    private function snapshot(DocumentTemplate $template): array
    {
        return $template->only(['name', 'feature_id', 'curriculum_id', 'education_level_id', 'paper_size', 'orientation', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'font_family', 'font_size', 'line_height', 'is_active']);
    }
}
