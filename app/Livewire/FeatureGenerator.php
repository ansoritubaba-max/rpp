<?php

namespace App\Livewire;

use App\Livewire\Concerns\PersistsWizardDraft;
use App\Models\AcademicYear;
use App\Models\Curriculum;
use App\Models\EducationLevel;
use App\Models\Feature;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\VocationalProgram;
use App\Services\Academic\AcademicContextService;
use App\Services\AI\AllModelsUnavailableException;
use App\Services\Documents\DocumentContinuationService;
use App\Services\Documents\FailedGenerationDraftService;
use App\Services\Documents\GenericDocumentService;
use App\Services\Documents\QuestionAssetLibrary;
use App\Services\Subscription\QuotaService;
use App\Services\Subscription\SubscriptionAccessException;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class FeatureGenerator extends Component
{
    use PersistsWizardDraft;

    public const FEATURE_CODES = ['modul_ajar', 'silabus', 'lkpd', 'soal', 'kisi_kisi', 'prota', 'promes', 'atp'];

    private const DRAFT_FIELDS = [
        'step', 'sourceDocumentUuid', 'schoolId', 'academicYear', 'curriculumId', 'educationLevelId',
        'vocationalProgramId', 'gradeId', 'subjectId', 'semester', 'topic', 'subtopic', 'teacherNotes',
        'meetings', 'jp', 'learningModel', 'studentCondition', 'syllabusMode', 'effectiveWeeks',
        'jpPerWeek', 'activityCount', 'difficulty', 'activityType', 'readingAbility', 'questionType',
        'questionCount', 'illustratedQuestions', 'imageAsset', 'imageCount', 'hots', 'storyQuestions',
        'includeAnswer', 'includeExplanation', 'includeBlueprint', 'sourceMode', 'cognitiveLevel',
        'totalJp', 'topicList', 'learningOutcome', 'objectiveCount',
    ];

    public int $step = 1;

    public bool $showUpgradeOffer = false;

    public string $featureCode;

    public string $featureName;

    public ?string $sourceDocumentUuid = null;

    public ?int $schoolId = null;

    public string $academicYear = '2026/2027';

    public ?int $curriculumId = null;

    public ?int $educationLevelId = null;

    public ?int $vocationalProgramId = null;

    public ?int $gradeId = null;

    public ?int $subjectId = null;

    public int|string|null $semester = 1;

    public string $topic = '';

    public string $subtopic = '';

    public string $teacherNotes = '';

    public int|string|null $meetings = 2;

    public int|string|null $jp = 2;

    public string $learningModel = 'Problem Based Learning';

    public string $studentCondition = 'Heterogen';

    public string $syllabusMode = 'semester';

    public int|string|null $effectiveWeeks = 18;

    public int|string|null $jpPerWeek = 4;

    public int|string|null $activityCount = 3;

    public string $difficulty = 'sedang';

    public string $activityType = 'individu_kelompok';

    public string $readingAbility = 'normal';

    public string $questionType = 'pilihan_ganda';

    public int|string|null $questionCount = 10;

    public bool $illustratedQuestions = false;

    public string $imageAsset = 'apple';

    public int|string|null $imageCount = 3;

    public bool $hots = false;

    public bool $storyQuestions = false;

    public bool $includeAnswer = true;

    public bool $includeExplanation = true;

    public bool $includeBlueprint = true;

    public string $sourceMode = 'materi';

    public string $cognitiveLevel = 'C2-C4';

    public int|string|null $totalJp = 108;

    public string $topicList = '';

    public string $learningOutcome = '';

    public int|string|null $objectiveCount = 6;

    public function mount(string $featureCode): void
    {
        abort_unless(in_array($featureCode, self::FEATURE_CODES, true), 404);
        $feature = Feature::query()->where('code', $featureCode)->where('is_active', true)->firstOrFail();
        $this->featureCode = $feature->code;
        $this->featureName = $feature->name;
        $this->academicYear = AcademicYear::query()->where('is_active', true)->value('name') ?? $this->academicYear;
        $this->schoolId = auth()->user()->schools()->value('id');
        $this->curriculumId = Curriculum::query()->where('code', 'KM')->value('id');
        $this->educationLevelId = EducationLevel::query()->where('code', 'SD')->value('id');
        $this->learningOutcome = 'Peserta didik memahami konsep utama dan mampu menerapkannya sesuai tahap perkembangannya.';
        $this->restoreWizardDraft($this->featureCode, self::DRAFT_FIELDS);

        if (request()->filled('source')) {
            $this->sourceDocumentUuid = request()->string('source')->toString();
            $this->applySource();
            $this->step = 1;
        }
    }

    public function updated(): void
    {
        $this->persistDraft();
    }

    public function updatedSourceDocumentUuid(): void
    {
        $this->applySource();
        $this->persistDraft();
    }

    public function updatedSemester(): void
    {
        if (! in_array((string) $this->semester, ['1', '2'], true)) {
            $this->addError('semester', 'Pilih semester 1 atau 2.');
            $this->persistDraft();

            return;
        }

        $source = app(DocumentContinuationService::class)->find(auth()->user(), $this->featureCode, $this->sourceDocumentUuid);
        if ($source && $this->featureCode === 'promes') {
            $this->topicList = implode("\n", app(DocumentContinuationService::class)->context($source, 'promes', (int) $this->semester)['topics']);
        }
        $this->persistDraft();
    }

    public function updatedEducationLevelId(): void
    {
        $this->gradeId = null;
        $this->subjectId = null;
        $this->vocationalProgramId = null;
        $this->persistDraft();
    }

    public function updatedVocationalProgramId(): void
    {
        $this->subjectId = null;
        $this->persistDraft();
    }

    public function nextStep(): void
    {
        $this->validate($this->rulesForStep($this->step), $this->validationMessages(), $this->attributes());
        $this->step = min(3, $this->step + 1);
        $this->persistDraft();
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->persistDraft();
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->step) {
            $this->step = max(1, $step);
            $this->persistDraft();
        }
    }

    public function discardDraft(): mixed
    {
        $this->forgetWizardDraft($this->featureCode);

        return $this->redirectRoute('generator.create', ['featureCode' => $this->featureCode], navigate: true);
    }

    public function generate(QuotaService $quotaService, GenericDocumentService $documentService, AcademicContextService $academicContextService): mixed
    {
        $this->showUpgradeOffer = false;
        $this->validate(array_merge(...array_map(fn ($step) => $this->rulesForStep($step), range(1, 3))), $this->validationMessages(), $this->attributes());

        $user = auth()->user();
        $school = $user->schools()->find($this->schoolId);
        if (! $school) {
            $this->addError('schoolId', 'Silakan pilih sekolah milik akun Anda.');

            return null;
        }

        $curriculum = Curriculum::query()->findOrFail($this->curriculumId);
        $level = EducationLevel::query()->findOrFail($this->educationLevelId);
        $program = $level->code === 'SMK'
            ? VocationalProgram::query()->whereKey($this->vocationalProgramId)->where('education_level_id', $level->id)->where('is_active', true)->first()
            : null;
        if ($level->code === 'SMK' && ! $program) {
            $this->addError('vocationalProgramId', 'Pilih konsentrasi keahlian SMK yang aktif.');

            return null;
        }
        $grade = Grade::query()->where('education_level_id', $level->id)->findOrFail($this->gradeId);
        $subject = Subject::query()->whereKey($this->subjectId)->where('education_level_id', $level->id)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('vocational_program_id')->orWhere('vocational_program_id', $program?->id))->first();
        if (! $subject) {
            $this->addError('subjectId', 'Mata pelajaran tidak sesuai dengan konsentrasi yang dipilih.');

            return null;
        }
        $clean = fn (string $value) => trim(strip_tags($value));

        if ($this->featureCode === 'atp' && $curriculum->code !== 'KM') {
            $this->addError('curriculumId', 'ATP hanya tersedia untuk Kurikulum Merdeka.');

            return null;
        }

        if ($this->featureCode === 'kisi_kisi' && $this->sourceMode === 'dokumen' && blank($this->sourceDocumentUuid)) {
            $this->addError('sourceDocumentUuid', 'Pilih dokumen soal sebagai sumber kisi-kisi.');

            return null;
        }

        $payload = [
            'school_id' => $school->id,
            'school_name' => $school->name,
            'teacher_name' => $user->name,
            'academic_year' => $this->academicYear,
            'curriculum_id' => $curriculum->id,
            'curriculum_name' => $curriculum->name,
            'education_level_id' => $level->id,
            'level_name' => $level->name,
            'vocational_program_id' => $program?->id,
            'vocational_program_name' => $program?->name,
            'grade_id' => $grade->id,
            'grade_name' => $grade->name,
            'grade_numeric' => $grade->numeric_value,
            'grade_phase' => $grade->phase,
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'semester' => (int) $this->semester,
            'curriculum_context' => $academicContextService->forSelection($curriculum->id, $level->id, $grade->id, $subject->id, (int) $this->semester, $program?->id),
            'topic' => $clean($this->topic),
            'subtopic' => $clean($this->subtopic),
            'teacher_notes' => $clean($this->teacherNotes),
            'settings' => [
                'meetings' => (int) $this->meetings,
                'jp' => (int) $this->jp,
                'learning_model' => $this->learningModel,
                'student_condition' => $this->studentCondition,
                'syllabus_mode' => $this->syllabusMode,
                'effective_weeks' => (int) $this->effectiveWeeks,
                'jp_per_week' => (int) $this->jpPerWeek,
                'activity_count' => (int) $this->activityCount,
                'difficulty' => $this->difficulty,
                'activity_type' => $this->activityType,
                'reading_ability' => $this->readingAbility,
                'question_type' => $this->questionType,
                'question_count' => (int) $this->questionCount,
                'illustrated_questions' => $this->illustratedQuestions || ($this->featureCode === 'soal' && (int) $grade->numeric_value <= 3 && $this->readingAbility === 'belum_lancar'),
                'image_asset' => $this->imageAsset,
                'image_count' => (int) $this->imageCount,
                'hots' => $this->hots,
                'story_questions' => $this->storyQuestions,
                'include_answer' => $this->includeAnswer,
                'include_explanation' => $this->includeExplanation,
                'include_blueprint' => $this->includeBlueprint,
                'source_mode' => $this->sourceMode,
                'cognitive_level' => $this->cognitiveLevel,
                'total_jp' => (int) $this->totalJp,
                'topic_list' => $clean($this->topicList),
                'learning_outcome' => $clean($this->learningOutcome),
                'objective_count' => (int) $this->objectiveCount,
            ],
        ];

        $continuation = app(DocumentContinuationService::class);
        if (filled($this->sourceDocumentUuid)) {
            $source = $continuation->find($user, $this->featureCode, $this->sourceDocumentUuid);
            if (! $source) {
                $this->addError('sourceDocumentUuid', 'Dokumen sumber tidak tersedia untuk akun atau jenis perangkat ini.');

                return null;
            }

            try {
                $continuation->assertCompatible($source, $payload, $this->featureCode);
            } catch (DomainException $error) {
                $this->addError('sourceDocumentUuid', $error->getMessage());

                return null;
            }

            $payload['source_document_id'] = $source->id;
            $payload['source_context'] = $continuation->context($source, $this->featureCode, (int) $this->semester, $payload['topic']);

            if ($this->featureCode === 'kisi_kisi') {
                $payload['settings']['source_mode'] = 'dokumen';
            }

            if ($this->featureCode === 'kisi_kisi' && $this->sourceMode === 'dokumen' && empty($payload['source_context']['questions'])) {
                $this->addError('sourceDocumentUuid', 'Dokumen sumber belum memiliki butir soal untuk dibuatkan kisi-kisi.');

                return null;
            }
        }

        $this->persistDraft();

        try {
            $document = $quotaService->runGeneration(
                $user,
                $this->featureCode,
                fn (Feature $feature) => $documentService->create($user, $feature, $payload),
            );
        } catch (DomainException $error) {
            $this->showUpgradeOffer = $error instanceof SubscriptionAccessException;
            $this->addError('generate', $error->getMessage());

            return null;
        } catch (AllModelsUnavailableException $error) {
            try {
                $feature = Feature::query()->where('code', $this->featureCode)->firstOrFail();
                $draft = app(FailedGenerationDraftService::class)->save($user, $feature, $payload);
                session()->flash('success', 'Semua model AI sedang tidak tersedia. Dokumen disimpan sebagai draf dan kuota Anda tidak berkurang.');

                return $this->redirectRoute('documents.show', $draft, navigate: true);
            } catch (Throwable $draftError) {
                Log::error('Failed document could not be saved as draft', ['user_id' => $user->id, 'feature' => $this->featureCode, 'exception' => $draftError]);
                $this->addError('generate', 'AI sedang tidak tersedia. Data formulir tetap tersimpan, tetapi draf dokumen belum berhasil dibuat.');

                return null;
            }
        } catch (Throwable $error) {
            Log::error('Document generation failed', ['user_id' => $user->id, 'feature' => $this->featureCode, 'exception' => $error]);
            $this->addError('generate', 'Dokumen belum berhasil dibuat. Silakan periksa data lalu coba kembali.');

            return null;
        }

        try {
            app(FailedGenerationDraftService::class)->resolve($user, $document->feature, $payload['topic']);
        } catch (Throwable $error) {
            Log::warning('Completed document retained an obsolete AI failure draft', ['user_id' => $user->id, 'feature' => $this->featureCode, 'exception' => $error]);
        }
        $this->forgetWizardDraft($this->featureCode);
        session()->flash('success', "{$this->featureName} berhasil dibuat dan siap ditinjau.");

        return $this->redirectRoute('documents.show', $document, navigate: true);
    }

    private function rulesForStep(int $step): array
    {
        if ($step === 1) {
            return [
                'schoolId' => ['required', 'integer'],
                'academicYear' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
                'curriculumId' => ['required', 'exists:curriculums,id'],
                'educationLevelId' => ['required', 'exists:education_levels,id'],
                'vocationalProgramId' => [Rule::requiredIf(fn () => EducationLevel::query()->whereKey($this->educationLevelId)->value('code') === 'SMK'), 'nullable', 'integer', Rule::exists('vocational_programs', 'id')->where('education_level_id', $this->educationLevelId)->where('is_active', true)],
                'gradeId' => ['required', Rule::exists('grades', 'id')->where('education_level_id', $this->educationLevelId)],
                'subjectId' => ['required', Rule::exists('subjects', 'id')->where('education_level_id', $this->educationLevelId)->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('vocational_program_id')->orWhere('vocational_program_id', $this->vocationalProgramId))],
                'semester' => ['required', 'integer', 'in:1,2'],
                'sourceDocumentUuid' => ['nullable', 'uuid'],
            ];
        }

        if ($step === 2) {
            return array_merge([
                'topic' => ['required', 'string', 'min:3', 'max:180'],
                'subtopic' => ['nullable', 'string', 'max:180'],
            ], match ($this->featureCode) {
                'modul_ajar' => ['meetings' => ['required', 'integer', 'min:1', 'max:20'], 'jp' => ['required', 'integer', 'min:1', 'max:12'], 'learningModel' => ['required', 'string', 'max:100'], 'studentCondition' => ['required', 'string', 'max:100']],
                'silabus' => ['syllabusMode' => ['required', 'in:semester,satu_materi'], 'effectiveWeeks' => ['required', 'integer', 'min:1', 'max:30'], 'jpPerWeek' => ['required', 'integer', 'min:1', 'max:20']],
                'lkpd' => ['activityCount' => ['required', 'integer', 'min:1', 'max:10'], 'difficulty' => ['required', 'in:mudah,sedang,sulit'], 'activityType' => ['required', 'in:individu,kelompok,individu_kelompok'], 'readingAbility' => ['required', 'in:belum_lancar,dasar,normal']],
                'soal' => ['questionType' => ['required', 'in:pilihan_ganda,uraian,campuran'], 'questionCount' => ['required', 'integer', 'in:5,10,20,25,50'], 'difficulty' => ['required', 'in:mudah,sedang,sulit'], 'readingAbility' => ['required', 'in:belum_lancar,dasar,normal'], 'illustratedQuestions' => ['boolean'], 'imageAsset' => ['required', Rule::in(app(QuestionAssetLibrary::class)->keys())], 'imageCount' => ['required', 'integer', 'min:1', 'max:8'], 'hots' => ['boolean'], 'storyQuestions' => ['boolean'], 'includeAnswer' => ['boolean'], 'includeExplanation' => ['boolean'], 'includeBlueprint' => ['boolean']],
                'kisi_kisi' => ['sourceMode' => ['required', 'in:materi,dokumen'], 'questionCount' => ['required', 'integer', 'min:5', 'max:50'], 'cognitiveLevel' => ['required', 'string', 'max:30']],
                'prota' => ['totalJp' => ['required', 'integer', 'min:10', 'max:500'], 'effectiveWeeks' => ['required', 'integer', 'min:1', 'max:52'], 'topicList' => ['nullable', 'string', 'max:3000']],
                'promes' => ['effectiveWeeks' => ['required', 'integer', 'min:1', 'max:30'], 'topicList' => ['nullable', 'string', 'max:3000']],
                'atp' => ['learningOutcome' => ['required', 'string', 'min:10', 'max:3000'], 'objectiveCount' => ['required', 'integer', 'min:2', 'max:20']],
                default => [],
            });
        }

        return ['teacherNotes' => ['nullable', 'string', 'max:1500']];
    }

    private function attributes(): array
    {
        return [
            'schoolId' => 'sekolah', 'academicYear' => 'tahun pelajaran', 'curriculumId' => 'kurikulum', 'vocationalProgramId' => 'konsentrasi keahlian',
            'educationLevelId' => 'jenjang', 'gradeId' => 'kelas', 'subjectId' => 'mata pelajaran',
            'topic' => 'materi utama', 'subtopic' => 'submateri', 'teacherNotes' => 'catatan guru',
            'semester' => 'semester', 'meetings' => 'jumlah pertemuan', 'jp' => 'JP per pertemuan',
            'effectiveWeeks' => 'minggu efektif', 'jpPerWeek' => 'JP per minggu', 'activityCount' => 'jumlah aktivitas',
            'totalJp' => 'total JP setahun', 'objectiveCount' => 'jumlah tujuan',
            'questionCount' => 'jumlah soal', 'topicList' => 'daftar materi', 'learningOutcome' => 'capaian pembelajaran',
            'imageAsset' => 'gambar soal', 'imageCount' => 'jumlah gambar awal',
        ];
    }

    private function validationMessages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'integer' => 'Kolom :attribute harus berupa angka bulat.',
            'min.numeric' => 'Kolom :attribute minimal :min.',
            'max.numeric' => 'Kolom :attribute maksimal :max.',
            'in' => 'Pilihan :attribute tidak valid.',
        ];
    }

    private function persistDraft(): void
    {
        $this->persistWizardDraft($this->featureCode, self::DRAFT_FIELDS);
    }

    public function render()
    {
        return view('livewire.feature-generator', [
            'sourceDocuments' => app(DocumentContinuationService::class)->available(auth()->user(), $this->featureCode, $this->sourceDocumentUuid),
            'schools' => auth()->user()->schools()->orderBy('name')->get(),
            'curriculums' => Curriculum::query()->where('is_active', true)->get(),
            'levels' => EducationLevel::query()->orderBy('sort_order')->get(),
            'isSmk' => EducationLevel::query()->whereKey($this->educationLevelId)->value('code') === 'SMK',
            'vocationalPrograms' => $this->educationLevelId
                ? VocationalProgram::query()->where('education_level_id', $this->educationLevelId)->where('is_active', true)->orderBy('sort_order')->get()
                : collect(),
            'grades' => $this->educationLevelId ? Grade::query()->where('education_level_id', $this->educationLevelId)->orderBy('sort_order')->get() : collect(),
            'subjects' => $this->educationLevelId ? Subject::query()->where('education_level_id', $this->educationLevelId)->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('vocational_program_id')->orWhere('vocational_program_id', $this->vocationalProgramId))
                ->orderBy('name')->get() : collect(),
            'questionAssets' => app(QuestionAssetLibrary::class)->all(),
            'visualQuestionMode' => $this->illustratedQuestions || ($this->featureCode === 'soal' && (int) Grade::query()->whereKey($this->gradeId)->value('numeric_value') <= 3 && $this->readingAbility === 'belum_lancar'),
        ])->title("Buat {$this->featureName}");
    }

    private function applySource(): void
    {
        if (blank($this->sourceDocumentUuid)) {
            return;
        }

        $continuation = app(DocumentContinuationService::class);
        $source = $continuation->find(auth()->user(), $this->featureCode, $this->sourceDocumentUuid);
        if (! $source) {
            $this->addError('sourceDocumentUuid', 'Dokumen sumber tidak tersedia untuk akun atau jenis perangkat ini.');

            return;
        }

        foreach ($continuation->prefill($source, $this->featureCode) as $property => $value) {
            $this->{$property} = $value;
        }

        if ($this->featureCode === 'kisi_kisi') {
            $this->sourceMode = 'dokumen';
            $this->questionCount = min(50, max(5, $source->questions()->count()));
        }

        $this->resetErrorBag('sourceDocumentUuid');
    }
}
