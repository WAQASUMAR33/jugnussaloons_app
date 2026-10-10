<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode', 50)->default('Cash');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->json('split_payments')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index('payment_date');
            $table->foreign('bank_account_id')->references('bankid')->on('bank_accounts')->onDelete('set null');
        });

        // Populate historical payments from existing appointments where paid_amount > 0
        $appointments = DB::table('appointments')
            ->where('paid_amount', '>', 0)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->get();

        foreach ($appointments as $apt) {
            $payDate = $apt->appointment_date 
                ? substr((string)$apt->appointment_date, 0, 10) 
                : substr((string)$apt->created_at, 0, 10);

            DB::table('appointment_payments')->insert([
                'appointment_id' => $apt->id,
                'payment_date' => $payDate,
                'amount' => $apt->paid_amount,
                'payment_mode' => $apt->payment_mode ?: 'Cash',
                'bank_account_id' => $apt->bank_account_id,
                'split_payments' => $apt->split_payments,
                'notes' => 'Initial payment migrated from appointment',
                'created_at' => $apt->created_at ?: now(),
                'updated_at' => $apt->updated_at ?: now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_payments');
    }
};
