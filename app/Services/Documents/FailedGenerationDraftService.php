<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FailedGenerationDraftService
{
    public function save(User $user, Feature $feature, array $data): Document
    {
        return DB::transaction(function () use ($user, $feature, $data) {
            $document = $this->pendingDraft($user, $feature, $data['topic']);

            $identity = [
                'school' => $data['school_name'],
                'teacher' => $data['teacher_name'],
                'subject' => $data['subject_name'],
                'class' => $data['grade_name'],
                'semester' => $data['semester'],
                'academic_year' => $data['academic_year'],
                'curriculum' => $data['curriculum_name'],
                'vocational_program' => $data['vocational_program_name'] ?? null,
                'topic' => $data['topic'],
            ];
            $content = $feature->code === 'rpp'
                ? ['identity' => $identity, 'objectives' => [], 'opening' => [], 'main_activity' => [], 'closing' => [], 'assessment' => []]
                : ['identity' => $identity, 'sections' => []];
            $attributes = [
                'user_id' => $user->id,
                'school_id' => $data['school_id'],
                'feature_id' => $feature->id,
                'source_document_id' => $data['source_document_id'] ?? null,
                'curriculum_id' => $data['curriculum_id'],
                'education_level_id' => $data['education_level_id'],
                'vocational_program_id' => $data['vocational_program_id'] ?? null,
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'academic_year' => $data['academic_year'],
                'semester' => $data['semester'],
                'title' => "{$feature->name} {$data['subject_name']} — {$data['topic']}",
                'topic' => $data['topic'],
                'subtopic' => $data['subtopic'] ?? null,
                'content_json' => $content,
                'metadata_json' => ['source' => 'ai-unavailable', 'settings' => $data['settings'] ?? [], 'retry_feature' => $feature->code],
                'status' => 'draft',
            ];

            if ($document) {
                $document->update($attributes);
            } else {
                $document = Document::query()->create(['uuid' => (string) Str::uuid()] + $attributes);
            }

            $document->versions()->updateOrCreate(
                ['version_number' => 1],
                ['content_json' => $content, 'created_by' => $user->id],
            );

            return $document;
        });
    }

    public function resolve(User $user, Feature $feature, string $topic): void
    {
        $this->pendingDraft($user, $feature, $topic)?->delete();
    }

    private function pendingDraft(User $user, Feature $feature, string $topic): ?Document
    {
        return Document::query()
            ->where('user_id', $user->id)
            ->where('feature_id', $feature->id)
            ->where('status', 'draft')
            ->where('topic', $topic)
            ->latest()
            ->get()
            ->first(fn (Document $candidate) => data_get($candidate->metadata_json, 'source') === 'ai-unavailable');
    }
}
