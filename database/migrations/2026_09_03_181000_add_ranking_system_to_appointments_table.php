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
            if (!Schema::hasColumn('appointments', 'ranking')) {
                $table->unsignedTinyInteger('ranking')->nullable()->after('status')->comment('1 to 5 stars rating/rank given to employee');
            }
            if (!Schema::hasColumn('appointments', 'ranking_notes')) {
                $table->text('ranking_notes')->nullable()->after('ranking')->comment('Feedback notes/review for the employee');
            }
            if (!Schema::hasColumn('appointments', 'ranked_by')) {
                $table->unsignedBigInteger('ranked_by')->nullable()->after('ranking_notes');
            }
            if (!Schema::hasColumn('appointments', 'ranked_at')) {
                $table->timestamp('ranked_at')->nullable()->after('ranked_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['ranking', 'ranking_notes', 'ranked_by', 'ranked_at']);
        });
    }
};
