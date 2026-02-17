<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('part_broken_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_part_id')->constrained('repair_parts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('part_batch_id')->nullable()->constrained('part_batches')->nullOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();

            $table->index(['repair_part_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_broken_records');
    }
};
