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
        Schema::table('appointment_services', function (Blueprint $table) {
            if (!Schema::hasColumn('appointment_services', 'quantity')) {
                $table->integer('quantity')->default(1)->after('custom_title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            if (Schema::hasColumn('appointment_services', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }
};
