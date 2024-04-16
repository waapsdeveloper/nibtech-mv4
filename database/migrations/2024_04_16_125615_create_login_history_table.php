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
        Schema::create('login_history', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('login_id')->index('login_id');
            $table->integer('admin_user_type');
            $table->string('username');
            $table->mediumText('country')->nullable();
            $table->mediumText('ip')->nullable();
            $table->mediumText('date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('login_history');
    }
};
