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
        Schema::create('marketplace_sync_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id');
            $table->string('sku', 255);
            $table->unsignedInteger('marketplace_id');
            $table->text('error_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_posted_on_marketplace')->default(false)->comment('Whether this SKU is actually posted on the marketplace');
            $table->integer('failure_count')->default(1)->comment('Number of times this SKU failed to sync');
            $table->timestamp('first_failed_at')->useCurrent();
            $table->timestamp('last_attempted_at')->useCurrent();
            $table->timestamps();
            
            // Unique constraint: one record per SKU per marketplace
            $table->unique(['sku', 'marketplace_id'], 'unique_sku_marketplace');
            
            // Indexes for faster queries
            $table->index('variation_id');
            $table->index('marketplace_id');
            $table->index('is_posted_on_marketplace');
            $table->index('last_attempted_at');
            
            // Foreign keys (optional, can be added if needed)
            // $table->foreign('variation_id')->references('id')->on('variation')->onDelete('cascade');
            // $table->foreign('marketplace_id')->references('id')->on('marketplace')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marketplace_sync_failures');
    }
};
