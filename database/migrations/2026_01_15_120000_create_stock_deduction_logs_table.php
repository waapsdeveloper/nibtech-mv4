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
        Schema::create('stock_deduction_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('variation_id')->comment('Variation ID');
            $table->integer('marketplace_id')->default(1)->comment('Marketplace ID');
            $table->integer('order_id')->nullable()->comment('Order ID');
            $table->string('order_reference_id', 255)->nullable()->comment('Order reference ID from marketplace');
            $table->string('variation_sku', 255)->nullable()->comment('Variation SKU for quick reference');
            
            // Stock values before deduction
            $table->integer('before_variation_stock')->default(0)->comment('Variation listed_stock before deduction');
            $table->integer('before_marketplace_stock')->default(0)->comment('Marketplace listed_stock before deduction');
            
            // Stock values after deduction
            $table->integer('after_variation_stock')->default(0)->comment('Variation listed_stock after deduction');
            $table->integer('after_marketplace_stock')->default(0)->comment('Marketplace listed_stock after deduction');
            
            // Deduction details
            $table->string('deduction_reason', 50)->comment('Reason: new_order_status_1 or status_change_1_to_2');
            $table->integer('order_status')->nullable()->comment('Order status at time of deduction');
            $table->boolean('is_new_order')->default(false)->comment('Whether this was a new order');
            $table->integer('old_order_status')->nullable()->comment('Previous order status (if status changed)');
            
            // Metadata
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->timestamp('deduction_at')->useCurrent()->comment('When the deduction occurred');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('variation_id');
            $table->index('order_id');
            $table->index('order_reference_id');
            $table->index('marketplace_id');
            $table->index('deduction_reason');
            $table->index('deduction_at');
            $table->index(['variation_id', 'deduction_at']);
            $table->index(['order_id', 'deduction_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_deduction_logs');
    }
};
