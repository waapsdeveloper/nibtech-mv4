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
        if (Schema::hasTable('marketplace_stock_history')) {
            return;
        }

        Schema::create('marketplace_stock_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_stock_id')->nullable();
            $table->unsignedBigInteger('variation_id');
            $table->unsignedInteger('marketplace_id');
            $table->integer('listed_stock_before');
            $table->integer('listed_stock_after');
            $table->integer('locked_stock_before')->default(0);
            $table->integer('locked_stock_after')->default(0);
            $table->integer('available_stock_before')->default(0);
            $table->integer('available_stock_after')->default(0);
            $table->integer('quantity_change');
            $table->enum('change_type', [
                'order_created', 'order_completed', 'order_cancelled',
                'topup', 'manual', 'reconciliation', 'api_sync', 'lock', 'unlock'
            ]);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('reference_id')->nullable();
            $table->unsignedInteger('admin_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['variation_id', 'marketplace_id']);
            $table->index('created_at');
            $table->index('order_id');
            $table->index('change_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marketplace_stock_history');
    }
};
