<?php

namespace App\Http\Requests;

use App\Models\Question;
use App\Services\Documents\QuestionAssetLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = $this->route('question');

        return $question instanceof Question && $question->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        /** @var Question $question */
        $question = $this->route('question');
        $optionIds = $question->options()->pluck('id')->all();

        $rules = [
            'topic' => ['required', 'string', 'max:255'],
            'subtopic' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['required', Rule::in(['mudah', 'sedang', 'sulit'])],
            'cognitive_level' => ['nullable', 'string', 'max:30'],
            'question_text' => ['required', 'string', 'max:10000'],
            'image_asset' => ['nullable', 'string', Rule::in(app(QuestionAssetLibrary::class)->keys())],
            'image_count' => ['nullable', 'required_with:image_asset', 'integer', 'min:1', 'max:10'],
            'answer_text' => [$optionIds === [] ? 'required' : 'nullable', 'string', 'max:10000'],
            'explanation' => ['nullable', 'string', 'max:20000'],
            'options' => [$optionIds === [] ? 'prohibited' : 'required', 'array', 'size:'.count($optionIds)],
            'options.*' => ['required', 'string', 'max:5000'],
            'correct_option' => [$optionIds === [] ? 'prohibited' : 'required', 'integer', Rule::in($optionIds)],
        ];

        foreach ($optionIds as $optionId) {
            $rules['options.'.$optionId] = ['required', 'string', 'max:5000'];
        }

        return $rules;
    }
}
