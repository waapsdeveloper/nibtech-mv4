<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('part_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_part_id')->constrained('repair_parts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('batch_number')->comment('Client-facing batch/reference number');
            $table->integer('quantity_received')->default(0);
            $table->integer('quantity_remaining')->default(0)->comment('Decremented when parts are used in repairs');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->nullable()->comment('quantity_received * unit_cost');
            $table->date('received_at')->nullable();
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('repair_part_id');
            $table->index('received_at');
            $table->index(['repair_part_id', 'quantity_remaining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_batches');
    }
};
