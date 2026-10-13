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
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();

            if (!Schema::hasColumn('sale_items', 'custom_title')) {
                $table->string('custom_title')->nullable()->after('product_id');
            }

            if (!Schema::hasColumn('sale_items', 'is_addon')) {
                $table->boolean('is_addon')->default(false)->after('custom_title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'is_addon')) {
                $table->dropColumn('is_addon');
            }

            if (Schema::hasColumn('sale_items', 'custom_title')) {
                $table->dropColumn('custom_title');
            }

            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
