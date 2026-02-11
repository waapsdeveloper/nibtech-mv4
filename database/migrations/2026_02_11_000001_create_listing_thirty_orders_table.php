<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Parent table: BM listing snapshot from functions:thirty (exactly what came from BM).
     */
    public function up()
    {
        Schema::create('listing_thirty_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id')->nullable()->index()->comment('FK to variation.id');
            $table->string('country_code', 10)->nullable()->index();
            $table->string('bm_listing_id', 255)->index()->comment('BM listing_id (reference_id)');
            $table->string('bm_listing_uuid', 255)->nullable();
            $table->string('sku', 255)->nullable()->index();
            $table->string('source', 50)->default('get_listings')->comment('get_listings | get_listingsBi');
            $table->integer('quantity')->default(0);
            $table->tinyInteger('publication_state')->nullable()->comment('BM publication_state 0-4');
            $table->tinyInteger('state')->nullable()->comment('BM state (grade-related)');
            $table->string('title', 500)->nullable();
            $table->decimal('price_amount', 12, 2)->nullable();
            $table->string('price_currency', 10)->nullable();
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['variation_id', 'country_code', 'synced_at']);
            $table->index('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('listing_thirty_orders');
    }
};
