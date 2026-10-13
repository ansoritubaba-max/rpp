<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionSet;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankManagementFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_can_edit_and_duplicate_own_question_without_using_quota(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $question = $this->questionFor($teacher);
        $first = $question->options()->create(['label' => 'A', 'content' => 'Lama A', 'is_correct' => true, 'sort_order' => 1]);
        $second = $question->options()->create(['label' => 'B', 'content' => 'Lama B', 'is_correct' => false, 'sort_order' => 2]);

        $this->actingAs($teacher)->get(route('questions.edit', $question))->assertOk()->assertSee('Edit Soal');
        $this->patch(route('questions.update', $question), [
            'topic' => 'Pecahan Baru', 'difficulty' => 'sulit', 'question_text' => 'Manakah pecahan terbesar?',
            'explanation' => 'Bandingkan penyebutnya.',
            'options' => [$first->id => 'Satu per dua', $second->id => 'Satu per empat'],
            'correct_option' => $second->id,
        ])->assertRedirect(route('questions.index'));

        $this->assertSame('B', $question->fresh()->answer_text);
        $this->assertFalse($first->fresh()->is_correct);
        $this->assertTrue($second->fresh()->is_correct);
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'topic' => 'Pecahan Baru', 'difficulty' => 'sulit']);

        $this->post(route('questions.duplicate', $question))->assertRedirect(route('questions.index'));
        $copy = Question::query()->where('user_id', $teacher->id)->whereKeyNot($question->id)->firstOrFail();
        $this->assertSame('Pecahan Baru', $copy->topic);
        $this->assertSame(2, $copy->options()->count());
        $this->assertNull($copy->document_id);
        $this->assertSame('manual', $copy->source_type);
        $this->assertDatabaseCount('feature_usages', 0);
    }

    public function test_teacher_can_create_ordered_set_and_print_own_questions(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $first = $this->questionFor($teacher, 'Soal pertama');
        $second = $this->questionFor($teacher, 'Soal kedua');

        $this->actingAs($teacher)->get(route('questions.index'))->assertOk()->assertSee('Buat Paket Soal');
        $response = $this->post(route('question-sets.store'), [
            'title' => 'Latihan Pecahan',
            'question_ids' => [$second->id, $first->id],
        ]);

        $set = QuestionSet::query()->firstOrFail();
        $response->assertRedirect(route('question-sets.show', $set));
        $this->assertSame([$second->id, $first->id], $set->questions->pluck('id')->all());
        $this->get(route('question-sets.index'))->assertOk()->assertSee('Latihan Pecahan');
        $this->get(route('question-sets.show', $set))->assertOk()->assertSeeInOrder(['Soal kedua', 'Soal pertama']);
    }

    public function test_question_and_set_actions_reject_other_teacher_data(): void
    {
        $owner = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $other = User::factory()->create(['role' => 'guru', 'email_verified_at' => now()]);
        $question = $this->questionFor($owner);
        $set = $owner->questionSets()->create(['title' => 'Paket Rahasia']);
        $set->questions()->attach($question, ['sort_order' => 1]);

        $this->actingAs($other)->get(route('questions.edit', $question))->assertForbidden();
        $this->patch(route('questions.update', $question), ['topic' => 'Diubah'])->assertForbidden();
        $this->post(route('questions.duplicate', $question))->assertForbidden();
        $this->delete(route('questions.destroy', $question))->assertForbidden();
        $this->get(route('question-sets.show', $set))->assertForbidden();
        $this->delete(route('question-sets.destroy', $set))->assertForbidden();
        $this->post(route('question-sets.store'), ['title' => 'Paket', 'question_ids' => [$question->id]])
            ->assertSessionHasErrors('question_ids.0');

        $this->assertDatabaseHas('questions', ['id' => $question->id, 'user_id' => $owner->id]);
        $this->assertDatabaseCount('question_sets', 1);
    }

    public function test_deleting_question_removes_it_from_sets_but_deleting_set_preserves_question(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $first = $this->questionFor($teacher, 'Soal pertama');
        $second = $this->questionFor($teacher, 'Soal kedua');
        $set = $teacher->questionSets()->create(['title' => 'Latihan']);
        $set->questions()->attach([$first->id => ['sort_order' => 1], $second->id => ['sort_order' => 2]]);

        $this->actingAs($teacher)->delete(route('questions.destroy', $first))->assertRedirect();
        $this->assertDatabaseMissing('question_set_items', ['question_set_id' => $set->id, 'question_id' => $first->id]);
        $this->get(route('question-sets.show', $set))->assertOk()->assertDontSee('Soal pertama');
        $this->delete(route('question-sets.destroy', $set))->assertRedirect(route('question-sets.index'));
        $this->assertDatabaseHas('questions', ['id' => $second->id, 'deleted_at' => null]);
    }

    private function questionFor(User $user, string $text = 'Soal pecahan'): Question
    {
        return $user->questions()->create([
            'topic' => 'Pecahan',
            'question_type' => 'pilihan_ganda',
            'difficulty' => 'sedang',
            'question_text' => $text,
            'answer_text' => 'A',
            'source_type' => 'manual',
        ]);
    }
}
