<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Parts inventory uses its own purchase order table (same conceptual structure as
     * orders: reference_id, status, currency, processed_by) so we can use string
     * reference_id (e.g. batch number BR-20260302-0001) and a dedicated list/detail view.
     */
    public function up(): void
    {
        Schema::create('parts_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id', 64)->comment('Batch number or PO reference e.g. BR-20260302-0001');
            $table->unsignedTinyInteger('status')->default(2)->comment('2=pending, 3=approved');
            $table->unsignedInteger('currency')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable()->comment('admin id');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('reference_id');
            $table->index('status');
            $table->index('created_at');
        });

        if (! Schema::hasColumn('part_batches', 'parts_purchase_order_id')) {
            Schema::table('part_batches', function (Blueprint $table) {
                $table->foreignId('parts_purchase_order_id')->nullable()->after('order_id')
                    ->constrained('parts_purchase_orders')->nullOnDelete();
            });
        }

        // Backfill: copy existing Parts Batch Receive orders into parts_purchase_orders
        $partsOrderTypeId = (int) DB::table('multi_type')
            ->where('table_name', 'orders')
            ->where('name', 'Parts Batch Receive')
            ->value('id');
        if ($partsOrderTypeId) {
            $orders = DB::table('orders')
                ->where('order_type_id', $partsOrderTypeId)
                ->whereIn('id', function ($q) {
                    $q->select('order_id')->from('part_batches')->whereNotNull('order_id');
                })
                ->get();
            foreach ($orders as $order) {
                $ref = $order->reference ?? 'Legacy-' . $order->id;
                $id = DB::table('parts_purchase_orders')->insertGetId([
                    'reference_id' => $ref,
                    'status' => $order->status ?? 2,
                    'currency' => $order->currency ?? null,
                    'processed_by' => $order->processed_by ?? null,
                    'created_at' => $order->created_at ?? now(),
                    'updated_at' => $order->updated_at ?? now(),
                ]);
                DB::table('part_batches')->where('order_id', $order->id)->update([
                    'parts_purchase_order_id' => $id,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('part_batches', function (Blueprint $table) {
            $table->dropForeign(['parts_purchase_order_id']);
        });
        Schema::dropIfExists('parts_purchase_orders');
    }
};
