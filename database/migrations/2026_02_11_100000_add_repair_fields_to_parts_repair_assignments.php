<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parts_repair_assignments', function (Blueprint $table) {
            $table->foreignId('part_batch_id')->nullable()->after('repair_part_id')->constrained('part_batches')->nullOnDelete();
            $table->decimal('unit_cost', 12, 2)->nullable()->after('part_batch_id');
            $table->string('reference_id', 64)->nullable()->after('unit_cost')->comment('Repair reference ID');
            $table->unsignedBigInteger('customer_id')->nullable()->after('reference_id')->comment('Repairer (customer)');
        });
    }

    public function down(): void
    {
        Schema::table('parts_repair_assignments', function (Blueprint $table) {
            $table->dropForeign(['part_batch_id']);
            $table->dropColumn(['part_batch_id', 'unit_cost', 'reference_id', 'customer_id']);
        });
    }
};
