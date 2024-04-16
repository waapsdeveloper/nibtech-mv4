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
        Schema::create('process_stock', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('stock_id');
            $table->integer('process_id');
            $table->integer('admin_id');
            $table->string('description', 200)->nullable();
            $table->integer('status');
            $table->timestamp('created_at')->useCurrent();
            $table->integer('updated_at')->nullable();
            $table->integer('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('process_stock');
    }
};
