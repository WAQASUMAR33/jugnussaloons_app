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
        Schema::table('settings', function (Blueprint $table) {
            $table->text('pos_receipt_note')->nullable()->after('whatsapp_anniversary_message');
            $table->text('appointment_receipt_note')->nullable()->after('pos_receipt_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['pos_receipt_note', 'appointment_receipt_note']);
        });
    }
};
