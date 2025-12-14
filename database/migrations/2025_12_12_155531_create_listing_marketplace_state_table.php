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
        Schema::create('listing_marketplace_state', function (Blueprint $table) {
            $table->id();
            $table->integer('variation_id');
            $table->integer('marketplace_id');
            $table->integer('listing_id')->nullable()->comment('NULL for marketplace-level, set for listing-level');
            $table->integer('country_id')->nullable()->comment('For listing-level changes');
            
            // Current Values
            $table->decimal('min_handler', 10, 2)->nullable()->comment('Min handler value (marketplace level)');
            $table->decimal('price_handler', 10, 2)->nullable()->comment('Price handler value (marketplace level)');
            $table->boolean('buybox')->nullable()->comment('BuyBox status: 0=No, 1=Yes (listing level)');
            $table->decimal('buybox_price', 10, 2)->nullable()->comment('BuyBox price to win (listing level)');
            $table->decimal('min_price', 10, 2)->nullable()->comment('Min price (listing level)');
            $table->decimal('price', 10, 2)->nullable()->comment('Price (listing level)');
            
            // Metadata
            $table->integer('last_updated_by')->nullable()->comment('Who last updated this record');
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->unique(['variation_id', 'marketplace_id', 'listing_id', 'country_id'], 'unique_listing_state');
            $table->index(['variation_id', 'marketplace_id'], 'idx_variation_marketplace');
            $table->index('listing_id', 'idx_listing');
            $table->index('last_updated_at', 'idx_last_updated');
            
            // Foreign Keys
            $table->foreign('variation_id')->references('id')->on('variation')->onDelete('cascade');
            $table->foreign('marketplace_id')->references('id')->on('marketplace')->onDelete('cascade');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
            $table->foreign('last_updated_by')->references('id')->on('admin')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('listing_marketplace_state');
    }
};
