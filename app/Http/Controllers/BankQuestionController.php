<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateQuestionRequest;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankQuestionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $questions = Question::query()
            ->with(['options', 'subject', 'grade', 'document'])
            ->where('user_id', $user->id)
            ->when($request->filled('subject'), fn ($query) => $query->where('subject_id', $request->integer('subject')))
            ->when($request->filled('grade'), fn ($query) => $query->where('grade_id', $request->integer('grade')))
            ->when($request->filled('curriculum'), fn ($query) => $query->where('curriculum_id', $request->integer('curriculum')))
            ->when($request->filled('difficulty'), fn ($query) => $query->where('difficulty', $request->string('difficulty')))
            ->when($request->filled('type'), fn ($query) => $query->where('question_type', $request->string('type')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('question_text', 'like', '%'.$request->string('search').'%')
                ->orWhere('topic', 'like', '%'.$request->string('search').'%')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('bank-questions.index', [
            'questions' => $questions,
            'subjects' => Subject::query()
                ->whereIn('id', Question::query()->where('user_id', $user->id)->select('subject_id'))
                ->orderBy('name')
                ->get(),
            'grades' => Grade::query()
                ->whereIn('id', Question::query()->where('user_id', $user->id)->select('grade_id'))
                ->orderBy('numeric_value')
                ->get(),
            'curriculums' => Curriculum::query()
                ->whereIn('id', Question::query()->where('user_id', $user->id)->select('curriculum_id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(Request $request, Question $question): View
    {
        abort_unless($question->user_id === $request->user()->id, 403);

        return view('bank-questions.edit', ['question' => $question->load('options', 'subject', 'grade')]);
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($question, $data): void {
            $question->load('options');
            $correctOption = $question->options->firstWhere('id', (int) ($data['correct_option'] ?? 0));
            $question->update([
                'topic' => $data['topic'],
                'subtopic' => $data['subtopic'] ?? null,
                'difficulty' => $data['difficulty'],
                'cognitive_level' => $data['cognitive_level'] ?? null,
                'question_text' => $data['question_text'],
                'image_asset' => array_key_exists('image_asset', $data) ? ($data['image_asset'] ?: null) : $question->image_asset,
                'image_count' => array_key_exists('image_asset', $data) ? (empty($data['image_asset']) ? null : (int) $data['image_count']) : $question->image_count,
                'answer_text' => $correctOption?->label ?? $data['answer_text'],
                'explanation' => $data['explanation'] ?? null,
            ]);

            foreach ($question->options as $option) {
                $option->update([
                    'content' => $data['options'][$option->id],
                    'is_correct' => $option->id === $correctOption?->id,
                ]);
            }
        });

        return redirect()->route('questions.index')->with('success', 'Soal berhasil diperbarui. Dokumen sumber tidak berubah.');
    }

    public function duplicate(Request $request, Question $question): RedirectResponse
    {
        abort_unless($question->user_id === $request->user()->id, 403);

        DB::transaction(function () use ($question): void {
            $question->load('options');
            $copy = $question->replicate(['document_id', 'source_type']);
            $copy->document_id = null;
            $copy->source_type = 'manual';
            $copy->save();

            foreach ($question->options as $option) {
                $copy->options()->create($option->only(['label', 'content', 'is_correct', 'sort_order']));
            }
        });

        return redirect()->route('questions.index')->with('success', 'Salinan soal ditambahkan tanpa menggunakan kuota.');
    }

    public function destroy(Request $request, Question $question): RedirectResponse
    {
        abort_unless($question->user_id === $request->user()->id, 403);
        DB::transaction(function () use ($question): void {
            $question->questionSets()->detach();
            $question->delete();
        });

        return back()->with('success', 'Soal dihapus dari bank soal.');
    }
}
