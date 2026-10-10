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
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'discount')) {
                $table->decimal('discount', 10, 2)->default(0.00)->after('unit_price');
            }
            if (!Schema::hasColumn('sale_items', 'discount_type')) {
                $table->string('discount_type', 20)->default('fixed')->after('discount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'discount_type')) {
                $table->dropColumn('discount_type');
            }
            if (Schema::hasColumn('sale_items', 'discount')) {
                $table->dropColumn('discount');
            }
        });
    }
};
