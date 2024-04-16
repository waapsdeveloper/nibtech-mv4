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
        Schema::create('multi_type', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('table_name', 30);
            $table->integer('sort')->nullable();
            $table->string('name', 50);
            $table->string('description', 200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('multi_type');
    }
};
