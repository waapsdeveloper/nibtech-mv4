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
        Schema::create('testing', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('reference_id', 50)->nullable();
            $table->integer('variation_id')->nullable();
            $table->integer('stock_id')->nullable();
            $table->string('name', 50)->nullable();
            $table->bigInteger('imei')->nullable();
            $table->string('serial_number', 20)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('storage', 20)->nullable();
            $table->string('battery_health', 5)->nullable();
            $table->string('vendor_grade', 20)->nullable();
            $table->string('grade', 20)->nullable();
            $table->string('fault', 50)->nullable();
            $table->string('tester', 10)->nullable();
            $table->string('lot', 20)->nullable();
            $table->integer('order_id')->nullable();
            $table->integer('status')->nullable();
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
        Schema::dropIfExists('testing');
    }
};
