<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repair_part_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->nullable()->constrained('process')->nullOnDelete();
            $table->foreignId('process_stock_id')->nullable()->constrained('process_stock')->nullOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained('stock')->nullOnDelete();
            $table->foreignId('repair_part_id')->constrained('repair_parts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('admin')->nullOnDelete();
            $table->integer('qty')->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['process_id', 'process_stock_id']);
            $table->index(['stock_id', 'repair_part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_part_usages');
    }
};
