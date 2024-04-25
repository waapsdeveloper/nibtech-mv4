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
        Schema::create('stock_operations', function (Blueprint $table) {
            $table->id();
            $table->integer('stock_id')->nullable();
            $table->integer('order_id')->nullable();
            $table->integer('process_id')->nullable();
            $table->integer('old_variation_id')->nullable();
            $table->integer('new_variation_id')->nullable();
            $table->string('description', 200)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_operations_models');
    }
};
