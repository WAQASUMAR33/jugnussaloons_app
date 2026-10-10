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
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->foreignId('payroll_id')->nullable()->constrained('payrolls')->onDelete('set null');
            $table->string('month_year', 7); // Format: YYYY-MM (e.g. 2026-09)
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->date('advance_date');
            $table->string('payment_mode', 50)->default('Cash'); // Cash, Bank Transfer, Cheque, Other
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts', 'bankid')->onDelete('set null');
            $table->string('reason')->nullable();
            $table->string('status', 30)->default('pending_recovery'); // pending_recovery, recovered, waived
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['account_id', 'month_year']);
            $table->index('advance_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
    }
};
