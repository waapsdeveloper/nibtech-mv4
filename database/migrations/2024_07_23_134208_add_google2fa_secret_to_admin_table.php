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
        Schema::table('admin', function (Blueprint $table) {
            $table->string('google2fa_secret')->nullable();
        });
    }

    public function down()
    {
        Schema::table('admin', function (Blueprint $table) {
            $table->dropColumn('google2fa_secret');
        });
    }

};
