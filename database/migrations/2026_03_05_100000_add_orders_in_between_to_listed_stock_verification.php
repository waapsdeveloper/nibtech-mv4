<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add orders_in_between to listed_stock_verification.
     * Default 0 – count of orders in between (e.g. between verification snapshots).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('listed_stock_verification', function (Blueprint $table) {
            $table->unsignedInteger('orders_in_between')->default(0)->after('pending_orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('listed_stock_verification', function (Blueprint $table) {
            $table->dropColumn('orders_in_between');
        });
    }
};
