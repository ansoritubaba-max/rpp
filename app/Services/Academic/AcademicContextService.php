<?php

namespace App\Services\Academic;

use App\Models\CurriculumSubject;

class AcademicContextService
{
    public function forSelection(int $curriculumId, int $levelId, int $gradeId, int $subjectId, int $semester, ?int $vocationalProgramId = null): array
    {
        $query = CurriculumSubject::query()
            ->with(['learningOutcomes.objectives', 'competencies'])
            ->where('curriculum_id', $curriculumId)
            ->where('education_level_id', $levelId)
            ->where('grade_id', $gradeId)
            ->where('subject_id', $subjectId)
            ->where('is_active', true);
        $context = null;
        foreach ([$vocationalProgramId, null] as $programId) {
            foreach ([$semester, null] as $contextSemester) {
                $context = (clone $query)
                    ->where('vocational_program_id', $programId)
                    ->where('semester', $contextSemester)
                    ->first();
                if ($context) {
                    break 2;
                }
            }
        }

        if (! $context) {
            return ['outcomes' => [], 'objectives' => [], 'competencies' => [], 'sources' => []];
        }

        $officialOutcomes = $context->learningOutcomes->where('source_kind', 'official');
        $selectedOutcomes = $officialOutcomes->isNotEmpty() ? $officialOutcomes : $context->learningOutcomes;

        $sources = collect($selectedOutcomes->pluck('source_url')->all())
            ->merge($context->competencies->pluck('source_url')->all())
            ->filter(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL))
            ->unique()
            ->values()
            ->all();

        return [
            'outcomes' => $selectedOutcomes->pluck('content')->values()->all(),
            'objectives' => $context->learningOutcomes->flatMap(fn ($outcome) => $outcome->objectives->pluck('content'))->values()->all(),
            'competencies' => $context->competencies->map(fn ($competency) => trim(collect([$competency->type, $competency->code, $competency->content])->filter()->join(' ')))->values()->all(),
            'sources' => $sources,
        ];
    }
}
