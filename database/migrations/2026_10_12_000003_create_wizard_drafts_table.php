<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizard_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_code', 30);
            $table->json('payload_json');
            $table->timestamps();
            $table->unique(['user_id', 'feature_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wizard_drafts');
    }
};
