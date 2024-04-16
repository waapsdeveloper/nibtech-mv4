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
        Schema::create('variation', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('product_id')->nullable();
            $table->string('reference_id', 20)->nullable();
            $table->string('sku', 40)->nullable();
            $table->string('name', 256)->nullable();
            $table->integer('color')->nullable();
            $table->integer('storage')->nullable();
            $table->integer('grade')->nullable();
            $table->integer('stock')->nullable();
            $table->decimal('price', 10, 4)->nullable();
            $table->integer('status');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
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
        Schema::dropIfExists('variation');
    }
};
