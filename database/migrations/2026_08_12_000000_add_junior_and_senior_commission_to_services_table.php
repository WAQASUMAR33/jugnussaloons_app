<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('junior_commission', 12, 2)->default(0.00)->after('discounted_price');
            $table->decimal('senior_commission', 12, 2)->default(0.00)->after('junior_commission');
        });

        // Copy existing commission values over
        if (Schema::hasColumn('services', 'commission')) {
            DB::statement('UPDATE services SET junior_commission = commission, senior_commission = commission WHERE commission IS NOT NULL AND commission > 0');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['junior_commission', 'senior_commission']);
        });
    }
};
