<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add order_id to part_batches (link to purchase order) and register
     * order type "Parts Batch Receive" in multi_type so it appears in purchase list.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('part_batches', 'order_id')) {
            Schema::table('part_batches', function (Blueprint $table) {
                $table->unsignedInteger('order_id')->nullable()->after('repair_part_id')
                    ->comment('Link to purchase order (orders.id) when created from batch receive / Create PO');
            });
        }

        // Index for lookups (no foreign key to avoid type mismatch with orders.id across environments)
        if (Schema::hasColumn('part_batches', 'order_id')) {
            Schema::table('part_batches', function (Blueprint $table) {
                if (! $this->indexExists('part_batches', 'part_batches_order_id_index')) {
                    $table->index('order_id');
                }
            });
        }

        // Register new order type for Parts Batch Receive (id 20 to avoid conflict with existing 1-5 orders, 6-19 process)
        $exists = DB::table('multi_type')->where('table_name', 'orders')->where('name', 'Parts Batch Receive')->exists();
        if (! $exists) {
            $maxId = (int) DB::table('multi_type')->max('id');
            $newId = max(20, $maxId + 1);
            DB::table('multi_type')->insert([
                'id' => $newId,
                'table_name' => 'orders',
                'sort' => 6,
                'name' => 'Parts Batch Receive',
                'description' => 'Parts inventory batch receive / bulk purchase',
            ]);
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        $result = $conn->select("SELECT COUNT(*) as c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?", [$db, $table, $index]);

        return (int) ($result[0]->c ?? 0) > 0;
    }

    public function down(): void
    {
        DB::table('multi_type')->where('table_name', 'orders')->where('name', 'Parts Batch Receive')->delete();

        if (Schema::hasColumn('part_batches', 'order_id')) {
            Schema::table('part_batches', function (Blueprint $table) {
                $table->dropIndex(['order_id']);
            });
            Schema::table('part_batches', function (Blueprint $table) {
                $table->dropColumn('order_id');
            });
        }
    }
};
