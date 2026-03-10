<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repurpose table for dashboard "Total Listed vs Should Be" discrepancies.
     * Columns: listed_stock (current variation.listed_stock), should_be (computed per variation using same logic as dashboard widget).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('listing_available_stock_discrepancies', function (Blueprint $table) {
            $table->unsignedInteger('listed_stock')->nullable()->after('variation_id')->comment('Current variation.listed_stock');
            $table->unsignedInteger('should_be')->nullable()->after('listed_stock')->comment('Computed: graded stock (grade<6) - process type 22 - pending order items');
        });

        Schema::table('listing_available_stock_discrepancies', function (Blueprint $table) {
            $table->dropColumn(['available_count', 'stocks_table_count']);
        });

        Schema::table('listing_available_stock_discrepancies', function (Blueprint $table) {
            $table->integer('difference')->nullable()->change()->comment('listed_stock - should_be');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('listing_available_stock_discrepancies', function (Blueprint $table) {
            $table->unsignedInteger('available_count')->nullable()->after('variation_id');
            $table->unsignedInteger('stocks_table_count')->nullable()->after('available_count');
        });

        Schema::table('listing_available_stock_discrepancies', function (Blueprint $table) {
            $table->dropColumn(['listed_stock', 'should_be']);
        });

        Schema::table('listing_available_stock_discrepancies', function (Blueprint $table) {
            $table->integer('difference')->nullable(false)->change()->comment('stocks_table_count - available_count');
        });
    }
};
