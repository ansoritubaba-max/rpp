<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_analytics', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('documents_count')->default(0);
            $table->unsignedInteger('ai_requests_count')->default(0);
            $table->decimal('ai_cost', 14, 6)->default(0);
            $table->unsignedInteger('paid_payments_count')->default(0);
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_analytics');
    }
};
