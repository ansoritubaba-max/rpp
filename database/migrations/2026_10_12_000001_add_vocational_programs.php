<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocational_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('education_level_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['education_level_id', 'code']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('vocational_program_id')->nullable()->after('education_level_id')->constrained()->nullOnDelete();
        });

        Schema::table('curriculum_subjects', function (Blueprint $table) {
            $table->foreignId('vocational_program_id')->nullable()->after('education_level_id')->constrained()->nullOnDelete();
            $table->index(['curriculum_id', 'vocational_program_id', 'grade_id', 'subject_id', 'semester'], 'curriculum_vocational_lookup');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('vocational_program_id')->nullable()->after('education_level_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('vocational_program_id'));
        Schema::table('curriculum_subjects', function (Blueprint $table) {
            $table->dropIndex('curriculum_vocational_lookup');
            $table->dropConstrainedForeignId('vocational_program_id');
        });
        Schema::table('subjects', fn (Blueprint $table) => $table->dropConstrainedForeignId('vocational_program_id'));
        Schema::dropIfExists('vocational_programs');
    }
};
