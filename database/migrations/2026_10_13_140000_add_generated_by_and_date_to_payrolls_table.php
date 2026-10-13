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
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'generated_by')) {
                $table->foreignId('generated_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payrolls', 'generated_date')) {
                $table->timestamp('generated_date')->nullable()->after('generated_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'generated_by')) {
                $table->dropForeign(['generated_by']);
                $table->dropColumn('generated_by');
            }
            if (Schema::hasColumn('payrolls', 'generated_date')) {
                $table->dropColumn('generated_date');
            }
        });
    }
};
