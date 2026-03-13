<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add batch_reference to parts_purchase_orders to store the SKU from batch-receive
     * (generated or barcode-scanned) when creating a purchase order.
     */
    public function up(): void
    {
        Schema::table('parts_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('parts_purchase_orders', 'batch_reference')) {
                $table->string('batch_reference', 64)->nullable()->after('reference')
                    ->comment('SKU from batch-receive form (generated or barcode-scanned)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parts_purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('parts_purchase_orders', 'batch_reference')) {
                $table->dropColumn('batch_reference');
            }
        });
    }
};
