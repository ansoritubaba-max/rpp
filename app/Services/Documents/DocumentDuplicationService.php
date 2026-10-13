<?php

namespace App\Services\Documents;

use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentDuplicationService
{
    public function duplicateRpp(Document $original, array $input): Document
    {
        return DB::transaction(function () use ($original, $input) {
            $content = $original->content_json;
            $content['material']['topic'] = trim(strip_tags($input['topic']));
            $content['material']['subtopic'] = trim(strip_tags($input['subtopic'] ?? ''));
            $content['identity']['lesson_date'] = $input['lesson_date'] ?? null;

            $jp = (int) data_get($original->metadata_json, 'jp', 2);
            $duration = (int) data_get($original->metadata_json, 'jp_duration', 35);
            $content['identity']['time_allocation'] = "{$input['meetings']} pertemuan × {$jp} JP ({$duration} menit/JP)";

            $metadata = $original->metadata_json ?? [];
            $metadata['meetings'] = (int) $input['meetings'];
            $metadata['lesson_date'] = $input['lesson_date'] ?? null;
            $metadata['duplicated_from_document_id'] = $original->id;
            $metadata['source'] = 'duplicated';

            $copy = Document::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $original->user_id,
                'school_id' => $original->school_id,
                'feature_id' => $original->feature_id,
                'curriculum_id' => $original->curriculum_id,
                'education_level_id' => $original->education_level_id,
                'vocational_program_id' => $original->vocational_program_id,
                'grade_id' => $original->grade_id,
                'subject_id' => $original->subject_id,
                'academic_year' => $original->academic_year,
                'semester' => $original->semester,
                'title' => trim(strip_tags($input['title'])),
                'topic' => trim(strip_tags($input['topic'])),
                'subtopic' => trim(strip_tags($input['subtopic'] ?? '')) ?: null,
                'content_json' => $content,
                'metadata_json' => $metadata,
                'status' => 'draft',
            ]);

            $copy->versions()->create([
                'version_number' => 1,
                'content_json' => $content,
                'created_by' => $original->user_id,
            ]);

            return $copy;
        });
    }
}
