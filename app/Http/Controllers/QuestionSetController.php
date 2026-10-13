<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionSetRequest;
use App\Models\QuestionSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuestionSetController extends Controller
{
    public function index(Request $request): View
    {
        return view('question-sets.index', [
            'sets' => $request->user()->questionSets()
                ->withCount('questions')
                ->latest()
                ->paginate(12),
        ]);
    }

    public function store(StoreQuestionSetRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $set = DB::transaction(function () use ($request, $data): QuestionSet {
            $set = $request->user()->questionSets()->create(['title' => $data['title']]);

            foreach ($data['question_ids'] as $index => $questionId) {
                $set->questions()->attach($questionId, ['sort_order' => $index + 1]);
            }

            return $set;
        });

        return redirect()->route('question-sets.show', $set)->with('success', 'Paket soal berhasil dibuat.');
    }

    public function show(Request $request, QuestionSet $questionSet): View
    {
        abort_unless($questionSet->user_id === $request->user()->id, 403);

        return view('question-sets.show', [
            'set' => $questionSet->load(['questions.options', 'questions.subject', 'questions.grade']),
        ]);
    }

    public function destroy(Request $request, QuestionSet $questionSet): RedirectResponse
    {
        abort_unless($questionSet->user_id === $request->user()->id, 403);
        $questionSet->delete();

        return redirect()->route('question-sets.index')->with('success', 'Paket soal dihapus. Soal di Bank Soal tetap tersimpan.');
    }
}
