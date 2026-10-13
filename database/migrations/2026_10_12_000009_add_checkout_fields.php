<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->timestamp('discount_ends_at')->nullable();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('gateway')->nullable(); // Midtrans or future gateway identifier.
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('gateway_transaction_id')->nullable();
            $table->text('qr_url')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn([
            'gateway', 'gateway_order_id', 'gateway_transaction_id', 'qr_url', 'expires_at',
        ]));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['discount_percent', 'discount_ends_at']));
    }
};
