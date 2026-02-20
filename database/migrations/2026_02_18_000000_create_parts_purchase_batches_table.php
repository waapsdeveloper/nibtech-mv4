<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parts_purchase_batches', function (Blueprint $table) {
            $table->id();
            $table->string('system_barcode', 64)->unique()->comment('System-generated barcode; same for all items in batch');
            $table->string('manufacturer_barcode', 255)->nullable()->comment('Optional manufacturer/supplier barcode');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('manufacturer_barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_purchase_batches');
    }
};
