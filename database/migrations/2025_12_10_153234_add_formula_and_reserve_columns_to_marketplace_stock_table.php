<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('marketplace_stock', function (Blueprint $table) {
            // JSON column to store formula configuration
            // Structure: {"type": "percentage|fixed", "marketplaces": [{"marketplace_id": 1, "value": 50}, ...], "remaining_to_marketplace_1": true}
            $table->json('formula')->nullable()->after('listed_stock');
            
            // Reserve columns to track old and new values before/after stock changes
            $table->integer('reserve_old_value')->nullable()->after('formula')->comment('Stock value before change');
            $table->integer('reserve_new_value')->nullable()->after('reserve_old_value')->comment('Stock value after change');
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
            $table->dropColumn(['formula', 'reserve_old_value', 'reserve_new_value']);
        });
    }
};
