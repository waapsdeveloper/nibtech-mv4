<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records where "Available" (card) count differs from stocks table count for a variation.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('listing_available_stock_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id')->index();
            $table->unsignedInteger('available_count')->comment('From variation.available_stocks (card)');
            $table->unsignedInteger('stocks_table_count')->comment('From get_variation_available_stocks (details table)');
            $table->integer('difference')->comment('stocks_table_count - available_count');
            $table->string('variation_sku', 64)->nullable()->comment('Denormalized for display');
            $table->dateTime('detected_at')->comment('When the check ran');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_available_stock_discrepancies');
    }
};
