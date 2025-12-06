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
        Schema::create('marketplace_stock', function (Blueprint $table) {
            $table->id();
            $table->integer('variation_id');
            $table->integer('marketplace_id');
            $table->integer('listed_stock')->default(0);
            $table->integer('admin_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints
            $table->foreign('variation_id')->references('id')->on('variation')->onDelete('cascade');
            $table->foreign('marketplace_id')->references('id')->on('marketplace')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admin')->onDelete('set null');
            
            // Unique constraint: one stock record per variation per marketplace
            $table->unique(['variation_id', 'marketplace_id']);
            
            // Indexes for better query performance
            $table->index('variation_id');
            $table->index('marketplace_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marketplace_stock');
    }
};
