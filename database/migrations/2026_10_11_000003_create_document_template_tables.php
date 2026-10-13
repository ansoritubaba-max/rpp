<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_id')->nullable()->constrained('curriculums')->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('paper_size')->default('a4');
            $table->string('orientation')->default('portrait');
            $table->decimal('margin_top', 5, 2)->default(20);
            $table->decimal('margin_right', 5, 2)->default(18);
            $table->decimal('margin_bottom', 5, 2)->default(20);
            $table->decimal('margin_left', 5, 2)->default(18);
            $table->string('font_family')->default('DejaVu Sans');
            $table->decimal('font_size', 4, 1)->default(10);
            $table->decimal('line_height', 3, 2)->default(1.5);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['feature_id', 'curriculum_id', 'education_level_id', 'is_active'], 'document_template_lookup');
        });

        Schema::create('template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->string('section_key');
            $table->string('title');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('source_type')->default('static');
            $table->text('preset_content')->nullable();
            $table->string('ai_task_key')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['template_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_sections');
        Schema::dropIfExists('document_templates');
    }
};
