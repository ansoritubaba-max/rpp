<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('catalog_key', 40)->nullable()->unique();
            $table->decimal('reference_price', 14, 2)->nullable();
            $table->boolean('show_reference_price')->default(false);
            $table->boolean('is_recommended')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
        });
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn([
            'catalog_key', 'reference_price', 'show_reference_price', 'is_recommended', 'sort_order',
        ]));
    }
};
