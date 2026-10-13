<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Feature;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentAndTeacherAdminDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_document_filters_cover_subject_grade_date_status_and_all_feature_types_without_leaking_other_users(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $other = User::factory()->create(['role' => 'guru', 'email_verified_at' => now()]);
        $grades = Grade::query()->where('education_level_id', DB::table('education_levels')->where('code', 'SD')->value('id'))
            ->orderBy('numeric_value')->take(2)->get();
        $subjects = Subject::query()->where('education_level_id', $grades->first()->education_level_id)
            ->orderBy('id')->take(2)->get();

        $this->makeDocument($teacher, 'Silabus Pecahan', 'silabus', $subjects[0]->id, $grades[0]->id, 'generated', now()->subDays(2));
        $this->makeDocument($teacher, 'Silabus Lingkungan', 'silabus', $subjects[1]->id, $grades[1]->id, 'draft', now()->subDay());
        $this->makeDocument($teacher, 'RPP Pecahan', 'rpp', $subjects[0]->id, $grades[0]->id, 'edited', now());
        $this->makeDocument($other, 'Dokumen Rahasia Guru Lain', 'silabus', $subjects[0]->id, $grades[0]->id, 'generated', now()->subDays(2));

        $this->actingAs($teacher)->get(route('documents.index'))
            ->assertOk()->assertSee('Silabus Pecahan')->assertSee('Silabus Lingkungan')
            ->assertSee('RPP Pecahan')->assertDontSee('Dokumen Rahasia Guru Lain')
            ->assertSee('Semua mapel')->assertSee('Semua kelas');

        $this->get(route('documents.index', [
            'type' => 'silabus', 'subject_id' => $subjects[0]->id, 'grade_id' => $grades[0]->id,
            'status' => 'generated', 'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->subDays(2)->toDateString(),
        ]))->assertOk()->assertSee('Silabus Pecahan')->assertDontSee('Silabus Lingkungan')
            ->assertDontSee('RPP Pecahan')->assertDontSee('Dokumen Rahasia Guru Lain');

        $this->get(route('documents.index', ['status' => 'draft']))
            ->assertOk()->assertSee('Silabus Lingkungan')->assertDontSee('Silabus Pecahan');
        $this->get(route('documents.index', ['date_from' => 'not-a-date']))
            ->assertRedirect(route('documents.index'));
    }

    public function test_admin_sees_active_devices_and_usage_for_every_feature_including_history(): void
    {
        config()->set('session.driver', 'database');
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $active = $teacher->activeSubscription()->firstOrFail();
        $old = Subscription::query()->create([
            'user_id' => $teacher->id, 'plan_id' => $active->plan_id,
            'starts_at' => now()->subDays(60), 'expires_at' => now()->subDays(30), 'status' => 'expired',
        ]);
        $feature = Feature::query()->where('code', 'rpp')->firstOrFail();
        foreach ([[$active->id, 2, 1], [$old->id, 3, 2]] as [$subscriptionId, $generations, $regenerations]) {
            DB::table('feature_usages')->insert([
                'subscription_id' => $subscriptionId, 'feature_id' => $feature->id,
                'generation_count' => $generations, 'regeneration_count' => $regenerations,
                'period_start' => now()->toDateString(), 'period_end' => now()->addDays(30)->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('sessions')->insert([
            ['id' => 'teacher-session-live', 'user_id' => $teacher->id, 'ip_address' => '192.0.2.10',
                'user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/120.0', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'teacher-session-expired', 'user_id' => $teacher->id, 'ip_address' => '192.0.2.11',
                'user_agent' => 'Mozilla/5.0 (Windows) Firefox/120.0', 'payload' => '', 'last_activity' => now()->subDays(3)->timestamp],
        ]);

        $this->actingAs($admin)->get(route('admin.teachers.show', $teacher))
            ->assertOk()->assertSee('Penggunaan seluruh fitur')->assertSee('5 dibuat · 3 ulang')
            ->assertSee('2 / 30')->assertSee('Perangkat dan sesi aktif')->assertSee('1 sesi aktif')
            ->assertSee('Chrome · Komputer')->assertDontSee('Firefox · Komputer')
            ->assertDontSee('teacher-session-live');
    }

    private function makeDocument(User $user, string $title, string $featureCode, int $subjectId, int $gradeId, string $status, \Illuminate\Support\Carbon $createdAt): void
    {
        Document::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id,
            'feature_id' => Feature::query()->where('code', $featureCode)->value('id'),
            'subject_id' => $subjectId, 'grade_id' => $gradeId,
            'title' => $title, 'topic' => $title, 'content_json' => [], 'status' => $status,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }
}
