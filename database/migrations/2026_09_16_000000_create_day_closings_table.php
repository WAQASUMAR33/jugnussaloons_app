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
        Schema::create('day_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closing_date')->unique();
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('opening_bank', 14, 2)->default(0);
            $table->decimal('total_opening', 14, 2)->default(0);

            $table->decimal('cash_received', 14, 2)->default(0);
            $table->decimal('bank_received', 14, 2)->default(0);
            $table->decimal('total_received', 14, 2)->default(0);

            $table->decimal('cash_paid', 14, 2)->default(0);
            $table->decimal('bank_paid', 14, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);

            $table->decimal('net_cash_flow', 14, 2)->default(0);
            $table->decimal('net_bank_flow', 14, 2)->default(0);
            $table->decimal('total_net_flow', 14, 2)->default(0);

            $table->decimal('closing_cash_expected', 14, 2)->default(0);
            $table->decimal('closing_bank_expected', 14, 2)->default(0);
            $table->decimal('closing_total_expected', 14, 2)->default(0);

            $table->decimal('cash_actual_counted', 14, 2)->nullable();
            $table->decimal('cash_difference', 14, 2)->nullable(); // actual - expected
            $table->json('denominations')->nullable();

            $table->enum('status', ['open', 'closed', 'verified'])->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_closings');
    }
};
