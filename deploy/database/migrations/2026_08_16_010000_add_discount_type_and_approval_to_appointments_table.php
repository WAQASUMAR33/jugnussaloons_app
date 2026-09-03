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
            $table->enum('discount_type', ['fixed', 'percentage'])->default('fixed')->after('discount');
            $table->decimal('discount_percentage', 5, 2)->default(0.00)->after('discount_type');
            $table->enum('discount_status', ['approved', 'pending_approval', 'rejected'])->default('approved')->after('discount_percentage');
            $table->foreignId('discount_approved_by')->nullable()->after('discount_status')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['discount_approved_by']);
            $table->dropColumn(['discount_type', 'discount_percentage', 'discount_status', 'discount_approved_by']);
        });
    }
};
