<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parts_repair_assignments', function (Blueprint $table) {
            $table->id();
            // Match stock.id type (often int unsigned on existing DBs)
            $table->unsignedInteger('stock_id');
            $table->foreignId('repair_part_id')->constrained('repair_parts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('repaired_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();

            $table->foreign('stock_id')->references('id')->on('stock')->cascadeOnUpdate()->cascadeOnDelete();
            $table->index('stock_id');
            $table->index(['stock_id', 'repaired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_repair_assignments');
    }
};
