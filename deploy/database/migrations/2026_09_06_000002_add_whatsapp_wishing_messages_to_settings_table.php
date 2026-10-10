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
            $table->text('whatsapp_birthday_message')->nullable()->after('brand_phone2');
            $table->text('whatsapp_anniversary_message')->nullable()->after('whatsapp_birthday_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_birthday_message', 'whatsapp_anniversary_message']);
        });
    }
};
