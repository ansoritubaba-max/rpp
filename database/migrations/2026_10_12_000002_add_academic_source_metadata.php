<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_outcomes', function (Blueprint $table) {
            $table->string('source_url', 1000)->nullable();
            $table->string('source_kind', 30)->nullable();
        });

        Schema::table('learning_objectives', function (Blueprint $table) {
            $table->string('source_kind', 30)->nullable();
        });

        Schema::table('competencies', function (Blueprint $table) {
            $table->string('source_url', 1000)->nullable();
            $table->string('source_kind', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competencies', fn (Blueprint $table) => $table->dropColumn(['source_url', 'source_kind']));
        Schema::table('learning_objectives', fn (Blueprint $table) => $table->dropColumn('source_kind'));
        Schema::table('learning_outcomes', fn (Blueprint $table) => $table->dropColumn(['source_url', 'source_kind']));
    }
};
