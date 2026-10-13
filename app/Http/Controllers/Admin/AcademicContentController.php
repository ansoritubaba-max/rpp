<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompetencyRequest;
use App\Http\Requests\Admin\LearningObjectiveRequest;
use App\Http\Requests\Admin\LearningOutcomeRequest;
use App\Models\Competency;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\LearningObjective;
use App\Models\LearningOutcome;
use App\Models\Subject;
use App\Models\VocationalProgram;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicContentController extends Controller
{
    public function index(Request $request): View
    {
        $contextQuery = CurriculumSubject::query()
            ->when($request->integer('curriculum'), fn ($query, $id) => $query->where('curriculum_id', $id))
            ->when($request->integer('level'), fn ($query, $id) => $query->where('education_level_id', $id))
            ->when($request->integer('vocational_program'), fn ($query, $id) => $query->where('vocational_program_id', $id))
            ->when($request->integer('grade'), fn ($query, $id) => $query->where('grade_id', $id))
            ->when($request->integer('subject'), fn ($query, $id) => $query->where('subject_id', $id))
            ->when($request->integer('semester'), fn ($query, $semester) => $query->where(fn ($scope) => $scope->where('semester', $semester)->orWhereNull('semester')));
        $contextIds = $contextQuery->pluck('id');

        return view('admin.academic.content', [
            'curriculums' => Curriculum::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'levels' => EducationLevel::query()->with(['grades', 'subjects'])->orderBy('sort_order')->get(),
            'vocationalPrograms' => VocationalProgram::query()->with('educationLevel')->orderBy('name')->get(),
            'outcomes' => LearningOutcome::query()->with(['curriculumSubject.curriculum', 'curriculumSubject.educationLevel', 'curriculumSubject.vocationalProgram', 'curriculumSubject.grade', 'curriculumSubject.subject', 'objectives'])->whereIn('curriculum_subject_id', $contextIds)->latest()->paginate(12, ['*'], 'outcomes')->withQueryString(),
            'competencies' => Competency::query()->with(['curriculumSubject.curriculum', 'curriculumSubject.educationLevel', 'curriculumSubject.vocationalProgram', 'curriculumSubject.grade', 'curriculumSubject.subject'])->whereIn('curriculum_subject_id', $contextIds)->latest()->paginate(12, ['*'], 'competencies')->withQueryString(),
        ]);
    }

    public function storeOutcome(LearningOutcomeRequest $request, AuditService $auditService): RedirectResponse
    {
        $context = $this->resolveContext($request->validated());
        $outcome = $context->learningOutcomes()->create($request->safe()->only(['phase', 'element', 'content', 'source_url', 'source_kind']));
        $auditService->record('academic.outcome_created', $outcome, [], $outcome->toArray());

        return back()->with('success', 'Capaian pembelajaran berhasil ditambahkan.');
    }

    public function updateOutcome(LearningOutcomeRequest $request, LearningOutcome $outcome, AuditService $auditService): RedirectResponse
    {
        $before = $outcome->toArray();
        $context = $this->resolveContext($request->validated() + ['vocational_program_id' => $outcome->curriculumSubject->vocational_program_id]);
        $outcome->update($request->safe()->only(['phase', 'element', 'content', 'source_url', 'source_kind']) + ['curriculum_subject_id' => $context->id]);
        $auditService->record('academic.outcome_updated', $outcome, $before, $outcome->fresh()->toArray());

        return back()->with('success', 'Capaian pembelajaran berhasil diperbarui.');
    }

    public function storeCompetency(CompetencyRequest $request, AuditService $auditService): RedirectResponse
    {
        $context = $this->resolveContext($request->validated());
        $competency = $context->competencies()->create($request->safe()->only(['type', 'code', 'content', 'semester', 'source_url', 'source_kind']));
        $auditService->record('academic.competency_created', $competency, [], $competency->toArray());

        return back()->with('success', 'KI/KD/Indikator berhasil ditambahkan.');
    }

    public function updateCompetency(CompetencyRequest $request, Competency $competency, AuditService $auditService): RedirectResponse
    {
        $before = $competency->toArray();
        $context = $this->resolveContext($request->validated() + ['vocational_program_id' => $competency->curriculumSubject->vocational_program_id]);
        $competency->update($request->safe()->only(['type', 'code', 'content', 'semester', 'source_url', 'source_kind']) + ['curriculum_subject_id' => $context->id]);
        $auditService->record('academic.competency_updated', $competency, $before, $competency->fresh()->toArray());

        return back()->with('success', 'KI/KD/Indikator berhasil diperbarui.');
    }

    public function storeObjective(LearningObjectiveRequest $request, LearningOutcome $outcome, AuditService $auditService): RedirectResponse
    {
        $objective = $outcome->objectives()->create($request->validated());
        $auditService->record('academic.objective_created', $objective, [], $objective->toArray());

        return back()->with('success', 'Tujuan pembelajaran berhasil ditambahkan.');
    }

    public function updateObjective(LearningObjectiveRequest $request, LearningOutcome $outcome, LearningObjective $objective, AuditService $auditService): RedirectResponse
    {
        abort_unless($objective->learning_outcome_id === $outcome->id, 404);
        $before = $objective->toArray();
        $objective->update($request->validated());
        $auditService->record('academic.objective_updated', $objective, $before, $objective->fresh()->toArray());

        return back()->with('success', 'Tujuan pembelajaran berhasil diperbarui.');
    }

    private function resolveContext(array $data): CurriculumSubject
    {
        $gradeValid = Grade::query()->whereKey($data['grade_id'])->where('education_level_id', $data['education_level_id'])->exists();
        $programId = $data['vocational_program_id'] ?? null;
        $programValid = $programId === null || VocationalProgram::query()->whereKey($programId)->where('education_level_id', $data['education_level_id'])->exists();
        $subjectValid = Subject::query()->whereKey($data['subject_id'])
            ->where('education_level_id', $data['education_level_id'])
            ->where(fn ($query) => $query->whereNull('vocational_program_id')->orWhere('vocational_program_id', $programId))
            ->exists();
        if (! $gradeValid || ! $subjectValid || ! $programValid) {
            throw ValidationException::withMessages(['grade_id' => 'Kelas atau mata pelajaran tidak sesuai dengan jenjang yang dipilih.']);
        }

        return CurriculumSubject::query()->firstOrCreate([
            'curriculum_id' => $data['curriculum_id'],
            'education_level_id' => $data['education_level_id'],
            'vocational_program_id' => $programId,
            'grade_id' => $data['grade_id'],
            'subject_id' => $data['subject_id'],
            'semester' => $data['semester'] ?? null,
        ], ['is_active' => true]);
    }
}
