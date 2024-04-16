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
        Schema::create('orders', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('reference_id')->nullable()->index('reference_id');
            $table->integer('customer_id')->nullable();
            $table->integer('order_type_id');
            $table->integer('currency');
            $table->decimal('price', 11)->nullable();
            $table->string('delivery_note_url', 500)->nullable();
            $table->string('label_url', 500)->nullable();
            $table->string('tracking_number', 30)->nullable();
            $table->integer('status');
            $table->integer('processed_by')->nullable();
            $table->integer('linked_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
