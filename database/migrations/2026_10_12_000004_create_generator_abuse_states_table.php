<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generator_abuse_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('normal');
            $table->string('reason', 100)->nullable();
            $table->timestamp('warning_until')->nullable();
            $table->timestamp('blocked_until')->nullable();
            $table->timestamps();
            $table->index(['status', 'blocked_until']);
        });
        Schema::table('operational_events', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'created_at'], 'generator_events_user_type_time');
        });
    }

    public function down(): void
    {
        Schema::table('operational_events', function (Blueprint $table) {
            $table->dropIndex('generator_events_user_type_time');
        });
        Schema::dropIfExists('generator_abuse_states');
    }
};
