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
        Schema::create('admin', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('username', 20);
            $table->string('email', 256);
            $table->string('password', 256);
            $table->string('first_name', 128);
            $table->string('last_name', 128);
            $table->string('photo', 256)->nullable();
            $table->integer('role_id')->nullable();
            $table->integer('parent_id')->default(0);
            $table->mediumText('two_factor_secret')->nullable();
            $table->mediumText('two_factor_recovery_codes')->nullable();
            $table->dateTime('two_factor_confirmed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admin');
    }
};
