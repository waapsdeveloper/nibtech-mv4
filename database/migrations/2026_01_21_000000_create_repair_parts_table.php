<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repair_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('compatible_device')->nullable();
            $table->integer('on_hand')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnUpdate()->cascadeOnDelete();
            $table->index(['product_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_parts');
    }
};
