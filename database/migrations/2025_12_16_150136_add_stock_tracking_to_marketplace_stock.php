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
            $table->integer('locked_stock')->default(0)->after('listed_stock')->comment('Stock locked by pending orders');
            $table->integer('available_stock')->default(0)->after('locked_stock')->comment('listed_stock - locked_stock');
            $table->decimal('buffer_percentage', 5, 2)->default(10.00)->after('available_stock')->comment('Percentage to reduce when sending to marketplace');
            $table->timestamp('last_synced_at')->nullable()->after('buffer_percentage');
            $table->integer('last_api_quantity')->nullable()->after('last_synced_at');
            
            // Add indexes
            $table->index(['variation_id', 'marketplace_id'], 'idx_variation_marketplace');
            $table->index('available_stock', 'idx_available_stock');
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
            $table->dropIndex('idx_variation_marketplace');
            $table->dropIndex('idx_available_stock');
            $table->dropColumn(['locked_stock', 'available_stock', 'buffer_percentage', 'last_synced_at', 'last_api_quantity']);
        });
    }
};
