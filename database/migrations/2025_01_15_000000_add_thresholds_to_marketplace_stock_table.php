<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThresholdsToMarketplaceStockTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('marketplace_stock', function (Blueprint $table) {
            $table->integer('min_threshold')->nullable()->after('formula')->comment('Minimum stock threshold - if total stock below this, only add to BackMarket');
            $table->integer('max_threshold')->nullable()->after('min_threshold')->comment('Maximum stock threshold for this marketplace');
            $table->integer('min_stock_required')->nullable()->after('max_threshold')->comment('Minimum stock required before allowing additions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketplace_stock', function (Blueprint $table) {
            $table->dropColumn(['min_threshold', 'max_threshold', 'min_stock_required']);
        });
    }
}
