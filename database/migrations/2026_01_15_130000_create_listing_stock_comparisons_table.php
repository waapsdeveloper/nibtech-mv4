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
        Schema::create('listing_stock_comparisons', function (Blueprint $table) {
            $table->id();
            $table->integer('variation_id')->comment('Variation ID');
            $table->string('variation_sku', 255)->nullable()->comment('Variation SKU for quick reference');
            $table->integer('marketplace_id')->default(1)->comment('Marketplace ID (1 = BackMarket)');
            $table->string('country_code', 10)->nullable()->comment('Country code for the listing');
            
            // Stock values
            $table->integer('api_stock')->default(0)->comment('Stock quantity from BackMarket API');
            $table->integer('our_stock')->default(0)->comment('Our listed_stock value');
            $table->integer('pending_orders_count')->default(0)->comment('Number of pending orders for this variation');
            $table->integer('pending_orders_quantity')->default(0)->comment('Total quantity in pending orders');
            
            // Comparison calculations
            $table->integer('stock_difference')->default(0)->comment('Difference: our_stock - api_stock');
            $table->integer('available_after_pending')->default(0)->comment('Available stock after pending orders: our_stock - pending_orders_quantity');
            $table->integer('api_vs_pending_difference')->default(0)->comment('API stock vs pending: api_stock - pending_orders_quantity');
            
            // Status flags
            $table->boolean('is_perfect')->default(false)->comment('Whether stock matches perfectly (our_stock == api_stock)');
            $table->boolean('has_discrepancy')->default(false)->comment('Whether there is a discrepancy between API and our stock');
            $table->boolean('has_shortage')->default(false)->comment('Whether we have less stock than API');
            $table->boolean('has_excess')->default(false)->comment('Whether we have more stock than API');
            
            // Metadata
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->timestamp('compared_at')->useCurrent()->comment('When the comparison was made');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('variation_id');
            $table->index('marketplace_id');
            $table->index('compared_at');
            $table->index('is_perfect');
            $table->index('has_discrepancy');
            $table->index(['variation_id', 'compared_at']);
            $table->index(['marketplace_id', 'compared_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('listing_stock_comparisons');
    }
};
