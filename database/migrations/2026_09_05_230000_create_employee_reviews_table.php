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
        // Add image/avatar to accounts if not exists
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'image')) {
                $table->string('image')->nullable()->after('emp_type');
            }
        });

        Schema::create('employee_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->default(5)->comment('Rating from 1 to 5');
            $table->text('comments')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'rating']);
            $table->index('appointment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_reviews');

        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'image')) {
                $table->dropColumn('image');
            }
        });
    }
};
