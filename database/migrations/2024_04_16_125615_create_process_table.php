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
        Schema::create('process', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('order_id')->nullable();
            $table->integer('old_variation_id')->nullable();
            $table->integer('new_variation_id')->nullable();
            $table->integer('given_by_id')->nullable();
            $table->integer('taken_by_id')->nullable();
            $table->integer('process_type_id');
            $table->integer('quantity');
            $table->string('description', 200)->nullable();
            $table->integer('linked_id')->nullable();
            $table->integer('status');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('daleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('process');
    }
};
