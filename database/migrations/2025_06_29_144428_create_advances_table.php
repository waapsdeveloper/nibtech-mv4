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
        if (Schema::hasTable('advances')) {
            return;
        }

        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->decimal('amount', 10, 2);
            $table->string('reason')->nullable();
            $table->date('advance_date');
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
        Schema::dropIfExists('advances');
    }
};
