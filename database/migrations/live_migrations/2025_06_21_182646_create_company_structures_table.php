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
        Schema::create('company_structures', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g., department, team, project
            $table->string('name');
            $table->json('metadata')->nullable(); // Additional data: hours, rules, quota etc.
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
        Schema::dropIfExists('company_structures');
    }
};
