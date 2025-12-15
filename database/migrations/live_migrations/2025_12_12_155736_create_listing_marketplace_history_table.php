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
        Schema::create('listing_marketplace_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_id')->nullable()->comment('Reference to listing_marketplace_state');
            $table->integer('variation_id');
            $table->integer('marketplace_id');
            $table->integer('listing_id')->nullable()->comment('NULL for marketplace-level, set for listing-level');
            $table->integer('country_id')->nullable()->comment('For listing-level changes');
            
            // Field that changed
            $table->string('field_name', 50)->comment('min_handler, price_handler, buybox, buybox_price, min_price, price');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            
            // Change metadata
            $table->enum('change_type', ['marketplace', 'listing', 'bulk', 'auto'])->default('listing');
            $table->string('change_reason', 255)->nullable();
            $table->integer('admin_id')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Indexes
            $table->index('state_id', 'idx_state');
            $table->index(['variation_id', 'marketplace_id'], 'idx_variation_marketplace');
            $table->index('listing_id', 'idx_listing');
            $table->index('field_name', 'idx_field');
            $table->index('changed_at', 'idx_changed_at');
            $table->index(['variation_id', 'marketplace_id', 'changed_at'], 'idx_variation_marketplace_date');
            $table->index('admin_id', 'idx_admin');
            
            // Foreign Keys
            $table->foreign('state_id')->references('id')->on('listing_marketplace_state')->onDelete('cascade');
            $table->foreign('variation_id')->references('id')->on('variation')->onDelete('cascade');
            $table->foreign('marketplace_id')->references('id')->on('marketplace')->onDelete('cascade');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admin')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('listing_marketplace_history');
    }
};
