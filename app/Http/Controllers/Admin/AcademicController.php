<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcademicYearRequest;
use App\Http\Requests\Admin\CurriculumRequest;
use App\Http\Requests\Admin\EducationLevelRequest;
use App\Http\Requests\Admin\GradeRequest;
use App\Http\Requests\Admin\SubjectRequest;
use App\Http\Requests\Admin\VocationalProgramRequest;
use App\Models\AcademicYear;
use App\Models\Curriculum;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\VocationalProgram;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcademicController extends Controller
{
    public function index(): View
    {
        return view('admin.academic.index', [
            'curriculums' => Curriculum::query()->withCount('curriculumSubjects')->orderByDesc('is_active')->orderBy('name')->get(),
            'levels' => EducationLevel::query()->withCount(['grades', 'subjects', 'vocationalPrograms'])->orderBy('sort_order')->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('is_active')->orderByDesc('name')->get(),
        ]);
    }

    public function storeCurriculum(CurriculumRequest $request, AuditService $auditService): RedirectResponse
    {
        $curriculum = Curriculum::query()->create($request->validated());
        $auditService->record('academic.curriculum_created', $curriculum, [], $curriculum->toArray());

        return back()->with('success', 'Kurikulum berhasil ditambahkan.');
    }

    public function updateCurriculum(CurriculumRequest $request, Curriculum $curriculum, AuditService $auditService): RedirectResponse
    {
        $before = $curriculum->toArray();
        $curriculum->update($request->validated());
        $auditService->record('academic.curriculum_updated', $curriculum, $before, $curriculum->fresh()->toArray());

        return back()->with('success', 'Kurikulum berhasil diperbarui.');
    }

    public function storeLevel(EducationLevelRequest $request, AuditService $auditService): RedirectResponse
    {
        $level = EducationLevel::query()->create($request->validated());
        $auditService->record('academic.level_created', $level, [], $level->toArray());

        return back()->with('success', 'Jenjang berhasil ditambahkan.');
    }

    public function updateLevel(EducationLevelRequest $request, EducationLevel $level, AuditService $auditService): RedirectResponse
    {
        $before = $level->toArray();
        $level->update($request->validated());
        $auditService->record('academic.level_updated', $level, $before, $level->fresh()->toArray());

        return back()->with('success', 'Jenjang berhasil diperbarui.');
    }

    public function showLevel(EducationLevel $level): View
    {
        return view('admin.academic.level', ['level' => $level->load(['grades', 'vocationalPrograms', 'subjects.vocationalProgram'])]);
    }

    public function storeGrade(GradeRequest $request, EducationLevel $level, AuditService $auditService): RedirectResponse
    {
        $grade = $level->grades()->create($request->validated());
        $auditService->record('academic.grade_created', $grade, [], $grade->toArray());

        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function updateGrade(GradeRequest $request, EducationLevel $level, Grade $grade, AuditService $auditService): RedirectResponse
    {
        abort_unless($grade->education_level_id === $level->id, 404);
        $before = $grade->toArray();
        $grade->update($request->validated());
        $auditService->record('academic.grade_updated', $grade, $before, $grade->fresh()->toArray());

        return back()->with('success', 'Kelas berhasil diperbarui.');
    }

    public function storeSubject(SubjectRequest $request, EducationLevel $level, AuditService $auditService): RedirectResponse
    {
        $subject = $level->subjects()->create($request->validated());
        $auditService->record('academic.subject_created', $subject, [], $subject->toArray());

        return back()->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function updateSubject(SubjectRequest $request, EducationLevel $level, Subject $subject, AuditService $auditService): RedirectResponse
    {
        abort_unless($subject->education_level_id === $level->id, 404);
        $before = $subject->toArray();
        $subject->update($request->validated());
        $auditService->record('academic.subject_updated', $subject, $before, $subject->fresh()->toArray());

        return back()->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function storeVocationalProgram(VocationalProgramRequest $request, EducationLevel $level, AuditService $auditService): RedirectResponse
    {
        abort_unless($level->code === 'SMK', 404);
        $program = $level->vocationalPrograms()->create($request->validated());
        $auditService->record('academic.vocational_program_created', $program, [], $program->toArray());

        return back()->with('success', 'Konsentrasi keahlian berhasil ditambahkan.');
    }

    public function updateVocationalProgram(VocationalProgramRequest $request, EducationLevel $level, VocationalProgram $vocationalProgram, AuditService $auditService): RedirectResponse
    {
        abort_unless($level->code === 'SMK' && $vocationalProgram->education_level_id === $level->id, 404);
        $before = $vocationalProgram->toArray();
        $vocationalProgram->update($request->validated());
        $auditService->record('academic.vocational_program_updated', $vocationalProgram, $before, $vocationalProgram->fresh()->toArray());

        return back()->with('success', 'Konsentrasi keahlian berhasil diperbarui.');
    }

    public function storeAcademicYear(AcademicYearRequest $request, AuditService $auditService): RedirectResponse
    {
        $year = DB::transaction(function () use ($request) {
            if ($request->boolean('is_active')) {
                AcademicYear::query()->update(['is_active' => false]);
            }

            return AcademicYear::query()->create($request->validated());
        });
        $auditService->record('academic.year_created', $year, [], $year->toArray());

        return back()->with('success', 'Tahun pelajaran berhasil ditambahkan.');
    }

    public function updateAcademicYear(AcademicYearRequest $request, AcademicYear $academicYear, AuditService $auditService): RedirectResponse
    {
        $before = $academicYear->toArray();
        DB::transaction(function () use ($request, $academicYear) {
            if ($request->boolean('is_active')) {
                AcademicYear::query()->where('id', '!=', $academicYear->id)->update(['is_active' => false]);
            }
            $academicYear->update($request->validated());
        });
        $auditService->record('academic.year_updated', $academicYear, $before, $academicYear->fresh()->toArray());

        return back()->with('success', 'Tahun pelajaran berhasil diperbarui.');
    }
}
