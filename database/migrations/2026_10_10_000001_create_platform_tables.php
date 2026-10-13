<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('npsn')->nullable();
            $table->string('nsm')->nullable();
            $table->string('foundation_name')->nullable();
            $table->text('address');
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('principal_name')->nullable();
            $table->string('principal_identifier')->nullable();
            $table->string('logo_left')->nullable();
            $table->string('logo_right')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('curriculums', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('education_level_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('numeric_value');
            $table->string('phase')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['education_level_id', 'numeric_value']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('education_level_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_religious')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unique(['education_level_id', 'code']);
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->unsignedInteger('duration_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('generation_limit')->nullable();
            $table->unsignedInteger('regeneration_limit')->nullable();
            $table->unique(['plan_id', 'feature_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->string('status')->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status', 'expires_at']);
        });

        Schema::create('feature_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generation_count')->default(0);
            $table->unsignedInteger('regeneration_count')->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();
            $table->unique(['subscription_id', 'feature_id', 'period_start']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('plan_id')->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method');
            $table->date('payment_date');
            $table->string('proof_file')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('feature_id')->constrained();
            $table->foreignId('curriculum_id')->nullable()->constrained('curriculums')->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('academic_year')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('title');
            $table->string('topic')->nullable();
            $table->string('subtopic')->nullable();
            $table->json('content_json');
            $table->json('metadata_json')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'feature_id', 'created_at']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('content_json');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider_type');
            $table->string('base_url');
            $table->text('encrypted_api_key');
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('timeout_seconds')->default(45);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('name');
            $table->string('model_key');
            $table->unsignedSmallInteger('priority')->default(1);
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->unsignedInteger('timeout_seconds')->nullable();
            $table->decimal('estimated_input_cost', 12, 8)->nullable();
            $table->decimal('estimated_output_cost', 12, 8)->nullable();
            $table->unsignedInteger('internal_daily_limit')->nullable();
            $table->unsignedInteger('cooldown_seconds')->default(60);
            $table->timestamps();
        });

        Schema::create('ai_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['feature_id', 'ai_model_id']);
        });

        Schema::create('ai_model_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_id')->unique()->constrained('ai_models')->cascadeOnDelete();
            $table->string('status')->default('healthy');
            $table->text('last_error')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('feature_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0);
            $table->boolean('cache_hit')->default(false);
            $table->string('status')->index();
            $table->string('error_code')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
            $table->index(['created_at', 'model_id']);
        });

        Schema::create('ai_content_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_hash', 64)->unique();
            $table->string('task_key')->index();
            $table->json('input_signature_json');
            $table->json('output_json');
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'audit_logs', 'ai_content_cache', 'ai_usage_logs', 'ai_model_health',
            'ai_routes', 'ai_models', 'ai_providers', 'document_versions', 'documents',
            'payments', 'feature_usages', 'subscriptions', 'plan_features', 'plans',
            'features', 'subjects', 'grades', 'education_levels', 'curriculums', 'schools',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
