<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('payment_mode')->default('Cash')->after('total_commission');
            $table->decimal('extra_amount', 12, 2)->default(0.00)->after('payment_mode');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_mode')->default('Cash')->after('notes');
            $table->decimal('extra_amount', 12, 2)->default(0.00)->after('payment_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'extra_amount']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'extra_amount']);
        });
    }
};
