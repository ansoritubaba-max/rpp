<?php

namespace Tests\Feature;

use App\Livewire\FeatureGenerator;
use App\Livewire\RppWizard;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvalidGeneratorInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_silabus_rejects_letters_in_numeric_fields_without_a_server_error(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'silabus'])
            ->set('step', 2)
            ->set('topic', 'Pecahan sederhana')
            ->set('effectiveWeeks', 'bukan angka')
            ->set('jpPerWeek', 'empat')
            ->call('nextStep')
            ->assertHasErrors(['effectiveWeeks' => 'integer', 'jpPerWeek' => 'integer'])
            ->assertSee('Kolom minggu efektif harus berupa angka bulat.')
            ->assertSee('Kolom JP per minggu harus berupa angka bulat.')
            ->assertSet('step', 2);

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('feature_usages', 0);
    }

    public function test_other_generator_and_rpp_numeric_fields_are_validated_before_use(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();

        Livewire::actingAs($teacher)->test(FeatureGenerator::class, ['featureCode' => 'prota'])
            ->set('step', 2)->set('topic', 'Bilangan bulat')
            ->set('totalJp', 'seratus')->set('effectiveWeeks', '')
            ->call('nextStep')
            ->assertHasErrors(['totalJp' => 'integer', 'effectiveWeeks']);

        Livewire::actingAs($teacher)->test(RppWizard::class)
            ->set('step', 2)->set('topic', 'Bilangan bulat')
            ->set('meetings', 'dua')->set('jp', 'tiga')
            ->call('nextStep')
            ->assertHasErrors(['meetings' => 'integer', 'jp' => 'integer'])
            ->assertSee('Kolom jumlah pertemuan harus berupa angka bulat.')
            ->assertSet('step', 2);
    }
}
