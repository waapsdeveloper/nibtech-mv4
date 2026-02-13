<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops listing_thirty_*, stock_deduction_logs, and listing_stock_comparisons (features removed).
     */
    public function up()
    {
        Schema::dropIfExists('listing_thirty_order_refs');
        Schema::dropIfExists('listing_thirty_orders');
        Schema::dropIfExists('stock_deduction_logs');
        Schema::dropIfExists('listing_stock_comparisons');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Recreating is left to original migrations if ever needed
    }
};
