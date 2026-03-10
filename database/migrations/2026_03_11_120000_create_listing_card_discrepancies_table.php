<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variations where Stock (listed), Available (card), and Stocks table count don't all match.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('listing_card_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id')->index();
            $table->unsignedInteger('listed_stock')->comment('variation.listed_stock (Stock field)');
            $table->unsignedInteger('available_count')->comment('Card Available = available_stocks count');
            $table->unsignedInteger('stocks_table_count')->comment('Stocks table = countForListingStocksTable');
            $table->string('variation_sku', 64)->nullable();
            $table->dateTime('detected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_card_discrepancies');
    }
};
