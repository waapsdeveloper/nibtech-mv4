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
        Schema::create('marketplace_stock_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_stock_id');
            $table->unsignedBigInteger('variation_id');
            $table->unsignedInteger('marketplace_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->integer('quantity_locked');
            $table->enum('lock_status', ['locked', 'released', 'consumed'])->default('locked');
            $table->timestamp('locked_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('marketplace_stock_id');
            $table->index(['variation_id', 'marketplace_id']);
            $table->index('lock_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marketplace_stock_locks');
    }
};
