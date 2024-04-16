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
        Schema::create('customer', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('company', 200)->nullable();
            $table->string('first_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('street', 200)->nullable();
            $table->string('street2', 200)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->integer('country')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('reference', 50)->nullable();
            $table->integer('is_vendor')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
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
        Schema::dropIfExists('customer');
    }
};
