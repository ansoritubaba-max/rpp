<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        DB::table('academic_years')->insert([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('curriculum_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained('curriculums')->cascadeOnDelete();
            $table->foreignId('education_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['curriculum_id', 'education_level_id', 'grade_id', 'subject_id', 'semester'], 'curriculum_subject_lookup');
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_subject_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('code', 50)->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('semester')->nullable();
            $table->timestamps();
            $table->index(['curriculum_subject_id', 'type']);
        });

        Schema::create('learning_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_subject_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 5)->nullable();
            $table->string('element')->nullable();
            $table->text('content');
            $table->timestamps();
            $table->index(['curriculum_subject_id', 'phase']);
        });

        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_outcome_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->text('content');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_objectives');
        Schema::dropIfExists('learning_outcomes');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('curriculum_subjects');
        Schema::dropIfExists('academic_years');
    }
};
