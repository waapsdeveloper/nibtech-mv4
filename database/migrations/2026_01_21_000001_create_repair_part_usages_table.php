<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('repair_part_usages')) {
            return;
        }

        Schema::create('repair_part_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('process_id')->nullable();
            $table->unsignedInteger('process_stock_id')->nullable();
            $table->unsignedInteger('stock_id')->nullable();
            $table->foreignId('repair_part_id')->constrained('repair_parts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('technician_id')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['process_id', 'process_stock_id']);
            $table->index(['stock_id', 'repair_part_id']);

            $table->foreign('process_id')->references('id')->on('process')->nullOnDelete();
            $table->foreign('process_stock_id')->references('id')->on('process_stock')->nullOnDelete();
            $table->foreign('stock_id')->references('id')->on('stock')->nullOnDelete();
            $table->foreign('technician_id')->references('id')->on('admin')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_part_usages');
    }
};
