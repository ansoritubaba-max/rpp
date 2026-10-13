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
use App\Services\Documents\RppService;
use App\Services\Subscription\QuotaService;
use App\Services\Subscription\SubscriptionAccessException;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class RppWizard extends Component
{
    use PersistsWizardDraft;

    private const DRAFT_FIELDS = [
        'step', 'schoolId', 'academicYear', 'curriculumId', 'educationLevelId', 'vocationalProgramId', 'gradeId',
        'subjectId', 'semester', 'sourceDocumentUuid', 'topic', 'subtopic', 'meetings', 'jp', 'jpDuration',
        'learningModel', 'method', 'studentCondition', 'readingAbility', 'facilities', 'teacherNotes', 'components',
    ];

    public int $step = 1;

    public bool $showUpgradeOffer = false;

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

    public int|string|null $meetings = 1;

    public int|string|null $jp = 2;

    public int|string|null $jpDuration = 35;

    public string $learningModel = 'Problem Based Learning';

    public string $method = 'Diskusi, tanya jawab, dan penugasan';

    public string $studentCondition = 'Heterogen';

    public string $readingAbility = 'normal';

    public string $facilities = 'Papan tulis, buku teks, dan lembar aktivitas';

    public string $teacherNotes = '';

    public array $components = ['objectives', 'material', 'opening', 'main_activity', 'closing', 'assessment', 'remedial', 'enrichment', 'reflection'];

    public function mount(): void
    {
        $restored = $this->restoreWizardDraft('rpp', self::DRAFT_FIELDS);
        if (! $restored) {
            $legacyDraft = session('rpp_wizard_draft', []);
            foreach (self::DRAFT_FIELDS as $field) {
                if (array_key_exists($field, $legacyDraft)) {
                    $this->{$field} = $legacyDraft[$field];
                }
            }
            if ($legacyDraft !== []) {
                $this->persistDraft();
            }
        }
        session()->forget('rpp_wizard_draft');

        if (! $restored && empty($legacyDraft['academicYear'])) {
            $this->academicYear = AcademicYear::query()->where('is_active', true)->value('name') ?? $this->academicYear;
        }

        $this->schoolId ??= auth()->user()->schools()->value('id');
        $this->curriculumId ??= Curriculum::query()->where('code', 'KM')->value('id');
        $this->educationLevelId ??= EducationLevel::query()->where('code', 'SD')->value('id');

        if (request()->filled('source')) {
            $this->sourceDocumentUuid = request()->string('source')->toString();
            $this->applySource();
            $this->step = 1;
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'educationLevelId') {
            $this->gradeId = null;
            $this->subjectId = null;
            $this->vocationalProgramId = null;
            if (EducationLevel::query()->whereKey($this->educationLevelId)->value('code') === 'SMK') {
                $this->jpDuration = 45;
            }
        }

        if ($property === 'vocationalProgramId') {
            $this->subjectId = null;
        }

        if ($property === 'sourceDocumentUuid') {
            $this->applySource();
        }

        $this->persistDraft();
    }

    public function nextStep(): void
    {
        $this->validate($this->rulesForStep($this->step), $this->validationMessages(), $this->validationAttributes());
        $this->step = min(4, $this->step + 1);
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
        $this->forgetWizardDraft('rpp');
        session()->forget('rpp_wizard_draft');

        return $this->redirectRoute('rpp.create', navigate: true);
    }

    public function generate(QuotaService $quotaService, RppService $rppService, AcademicContextService $academicContextService): mixed
    {
        $this->showUpgradeOffer = false;
        $this->validate(array_merge(...array_map(fn ($step) => $this->rulesForStep($step), range(1, 4))), $this->validationMessages(), $this->validationAttributes());

        $user = auth()->user();
        $school = $user->schools()->find($this->schoolId);
        if (! $school) {
            $this->addError('schoolId', 'Silakan lengkapi profil sekolah terlebih dahulu.');

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
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'semester' => (int) $this->semester,
            'curriculum_context' => $academicContextService->forSelection($curriculum->id, $level->id, $grade->id, $subject->id, (int) $this->semester, $program?->id),
            'topic' => trim(strip_tags($this->topic)),
            'subtopic' => trim(strip_tags($this->subtopic)),
            'meetings' => (int) $this->meetings,
            'jp' => (int) $this->jp,
            'jp_duration' => (int) $this->jpDuration,
            'learning_model' => $this->learningModel,
            'method' => trim(strip_tags($this->method)),
            'student_condition' => $this->studentCondition,
            'reading_ability' => $this->readingAbility,
            'facilities' => trim(strip_tags($this->facilities)),
            'teacher_notes' => trim(strip_tags($this->teacherNotes)),
            'components' => $this->components,
        ];

        if (filled($this->sourceDocumentUuid)) {
            $continuation = app(DocumentContinuationService::class);
            $source = $continuation->find($user, 'rpp', $this->sourceDocumentUuid);
            if (! $source) {
                $this->addError('sourceDocumentUuid', 'Dokumen sumber tidak tersedia untuk akun atau jenis perangkat ini.');

                return null;
            }

            try {
                $continuation->assertCompatible($source, $payload, 'rpp');
            } catch (DomainException $error) {
                $this->addError('sourceDocumentUuid', $error->getMessage());

                return null;
            }

            $payload['source_document_id'] = $source->id;
            $payload['source_context'] = $continuation->context($source, 'rpp', (int) $this->semester, $payload['topic']);
        }

        $this->persistDraft();

        try {
            $document = $quotaService->runGeneration(
                $user,
                'rpp',
                fn (Feature $feature) => $rppService->create($user, $feature, $payload),
            );
        } catch (DomainException $error) {
            $this->showUpgradeOffer = $error instanceof SubscriptionAccessException;
            $this->addError('generate', $error->getMessage());

            return null;
        } catch (AllModelsUnavailableException $error) {
            try {
                $feature = Feature::query()->where('code', 'rpp')->firstOrFail();
                $draft = app(FailedGenerationDraftService::class)->save($user, $feature, $payload);
                session()->flash('success', 'Semua model AI sedang tidak tersedia. Dokumen disimpan sebagai draf dan kuota Anda tidak berkurang.');

                return $this->redirectRoute('documents.show', $draft, navigate: true);
            } catch (Throwable $draftError) {
                Log::error('Failed RPP could not be saved as document draft', ['user_id' => $user->id, 'exception' => $draftError]);
                $this->addError('generate', 'AI sedang tidak tersedia. Data formulir tetap tersimpan, tetapi draf dokumen belum berhasil dibuat.');

                return null;
            }
        } catch (Throwable $error) {
            Log::error('RPP generation failed', ['user_id' => $user->id, 'exception' => $error]);
            $this->addError('generate', 'Dokumen belum berhasil dibuat. Data formulir Anda sudah tersimpan. Silakan coba kembali.');

            return null;
        }

        try {
            app(FailedGenerationDraftService::class)->resolve($user, $document->feature, $payload['topic']);
        } catch (Throwable $error) {
            Log::warning('Completed RPP retained an obsolete AI failure draft', ['user_id' => $user->id, 'exception' => $error]);
        }
        $this->forgetWizardDraft('rpp');
        session()->flash('success', 'RPP berhasil disusun dan siap ditinjau.');

        return $this->redirectRoute('documents.show', $document, navigate: true);
    }

    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
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
            ],
            2 => [
                'topic' => ['required', 'string', 'min:3', 'max:180'],
                'subtopic' => ['nullable', 'string', 'max:180'],
                'meetings' => ['required', 'integer', 'min:1', 'max:20'],
                'jp' => ['required', 'integer', 'min:1', 'max:12'],
                'jpDuration' => ['required', 'integer', 'min:25', 'max:60'],
            ],
            3 => [
                'learningModel' => ['required', 'string', 'max:100'],
                'method' => ['required', 'string', 'max:300'],
                'studentCondition' => ['required', 'string', 'max:100'],
                'readingAbility' => ['required', 'in:belum_lancar,dasar,normal'],
                'facilities' => ['required', 'string', 'max:500'],
                'teacherNotes' => ['nullable', 'string', 'max:1000'],
            ],
            4 => ['components' => ['required', 'array', 'min:5']],
        };
    }

    private function validationAttributes(): array
    {
        return [
            'schoolId' => 'sekolah', 'academicYear' => 'tahun pelajaran', 'curriculumId' => 'kurikulum', 'vocationalProgramId' => 'konsentrasi keahlian',
            'educationLevelId' => 'jenjang', 'gradeId' => 'kelas', 'subjectId' => 'mata pelajaran',
            'topic' => 'materi', 'subtopic' => 'submateri', 'meetings' => 'jumlah pertemuan',
            'jp' => 'jumlah JP', 'jpDuration' => 'durasi JP', 'learningModel' => 'model pembelajaran',
            'method' => 'metode pembelajaran', 'studentCondition' => 'kondisi siswa',
            'readingAbility' => 'kemampuan membaca', 'facilities' => 'sarana', 'teacherNotes' => 'catatan guru',
            'components' => 'komponen dokumen',
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
        $this->persistWizardDraft('rpp', self::DRAFT_FIELDS);
    }

    public function render()
    {
        return view('livewire.rpp-wizard', [
            'sourceDocuments' => app(DocumentContinuationService::class)->available(auth()->user(), 'rpp', $this->sourceDocumentUuid),
            'schools' => auth()->user()->schools()->orderBy('name')->get(),
            'curriculums' => Curriculum::query()->where('is_active', true)->get(),
            'levels' => EducationLevel::query()->orderBy('sort_order')->get(),
            'isSmk' => EducationLevel::query()->whereKey($this->educationLevelId)->value('code') === 'SMK',
            'vocationalPrograms' => $this->educationLevelId
                ? VocationalProgram::query()->where('education_level_id', $this->educationLevelId)->where('is_active', true)->orderBy('sort_order')->get()
                : collect(),
            'grades' => $this->educationLevelId
                ? Grade::query()->where('education_level_id', $this->educationLevelId)->orderBy('sort_order')->get()
                : collect(),
            'subjects' => $this->educationLevelId
                ? Subject::query()->where('education_level_id', $this->educationLevelId)->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('vocational_program_id')->orWhere('vocational_program_id', $this->vocationalProgramId))
                    ->orderBy('name')->get()
                : collect(),
        ])->title('Buat RPP');
    }

    private function applySource(): void
    {
        if (blank($this->sourceDocumentUuid)) {
            return;
        }

        $continuation = app(DocumentContinuationService::class);
        $source = $continuation->find(auth()->user(), 'rpp', $this->sourceDocumentUuid);
        if (! $source) {
            $this->addError('sourceDocumentUuid', 'Dokumen sumber tidak tersedia untuk akun atau jenis perangkat ini.');

            return;
        }

        foreach ($continuation->prefill($source, 'rpp') as $property => $value) {
            $this->{$property} = $value;
        }

        $this->resetErrorBag('sourceDocumentUuid');
    }
}
