<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Increase reference_id to 255 chars to support UUIDs (36 chars).
     * Previously too short — caused: SQLSTATE[22001] Data too long for column 'reference_id'
     *
     * @return void
     */
    public function up()
    {
        Schema::table('variation', function (Blueprint $table) {
            $table->string('reference_id', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('variation', function (Blueprint $table) {
            $table->string('reference_id', 20)->nullable()->change();
        });
    }
};
