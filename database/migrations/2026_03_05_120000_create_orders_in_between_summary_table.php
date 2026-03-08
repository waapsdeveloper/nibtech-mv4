<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Summary of listed-stock:orders-in-between runs (one row, updated each run).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('orders_in_between_summary', function (Blueprint $table) {
            $table->id();
            $table->dateTime('last_run_at')->nullable();
            $table->unsignedInteger('total_updated')->default(0);
            $table->unsignedSmallInteger('days_back')->nullable()->comment('Null = all time');
            $table->boolean('backfill_only')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('orders_in_between_summary');
    }
};
