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
        Schema::create('order_items', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('order_id')->index('order_id');
            $table->integer('reference_id')->nullable();
            $table->integer('care_id')->nullable();
            $table->integer('variation_id')->nullable();
            $table->integer('stock_id')->nullable();
            $table->integer('quantity');
            $table->decimal('price', 11);
            $table->integer('status')->nullable();
            $table->integer('linked_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_items');
    }
};
