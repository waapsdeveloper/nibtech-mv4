<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Child table: order refs from refresh:new / refresh:orders linked to listing_thirty_orders.
     */
    public function up()
    {
        Schema::create('listing_thirty_order_refs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_thirty_order_id')->nullable()->index()->comment('FK to listing_thirty_orders.id');
            $table->unsignedBigInteger('order_id')->index()->comment('FK to orders.id');
            $table->unsignedBigInteger('order_item_id')->nullable()->index()->comment('FK to order_items.id');
            $table->unsignedBigInteger('variation_id')->nullable()->index();
            $table->string('bm_order_id', 255)->nullable()->index();
            $table->string('source_command', 50)->default('refresh:new')->comment('refresh:new | refresh:orders');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('listing_thirty_order_refs');
    }
};
