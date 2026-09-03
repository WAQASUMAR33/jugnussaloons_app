<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run performance indexing migrations for discount approval tables.
     */
    public function up(): void
    {
        $safeAddIndex = function ($table, $column) {
            try {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        $t->index($column);
                    });
                }
            } catch (\Throwable $e) {
                // Ignore duplicate index errors
            }
        };

        $safeAddIndex('appointments', 'discount_status');
        $safeAddIndex('discount_requests', 'appointment_id');
        $safeAddIndex('discount_requests', 'status');
    }

    /**
     * Reverse performance indexing migrations.
     */
    public function down(): void
    {
    }
};
