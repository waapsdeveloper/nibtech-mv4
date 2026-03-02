<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add vendor (customer_id) and reference (vendor reference string) to parts purchase orders
     * so the create form can collect them like the main purchase list.
     */
    public function up(): void
    {
        Schema::table('parts_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('parts_purchase_orders', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('processed_by')
                    ->comment('Vendor/supplier (customer.id where is_vendor)');
            }
            if (! Schema::hasColumn('parts_purchase_orders', 'reference')) {
                $table->string('reference', 255)->nullable()->after('reference_id')
                    ->comment('Vendor reference (e.g. 121PCS MALAYSIA 18.11)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parts_purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('parts_purchase_orders', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('parts_purchase_orders', 'reference')) {
                $table->dropColumn('reference');
            }
        });
    }
};
