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
        Schema::table('day_closings', function (Blueprint $table) {
            $table->decimal('next_day_opening_cash', 14, 2)->nullable()->after('notes');
            $table->decimal('next_day_opening_bank', 14, 2)->nullable()->after('next_day_opening_cash');
            $table->decimal('next_day_opening_total', 14, 2)->nullable()->after('next_day_opening_bank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->dropColumn(['next_day_opening_cash', 'next_day_opening_bank', 'next_day_opening_total']);
        });
    }
};
