<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parts_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_id')->comment('Stock/IMEI this purchase is attached to');
            $table->foreignId('repair_part_id')->constrained('repair_parts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable()->comment('Null when on lease / price to be decided later');
            $table->boolean('is_lease')->default(false)->comment('Part on lease; price decided later');
            $table->timestamp('price_set_at')->nullable()->comment('When price was set if was lease');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();

            $table->index('stock_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_purchases');
    }
};
