<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add batch_reference to orders so parts purchase and RMA (e.g. sales return)
     * orders can be grouped by a batch ID.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('batch_reference', 64)->nullable()->after('order_type_id')
                ->comment('Batch ID for grouping e.g. parts purchase or RMA orders');
            $table->index('batch_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['batch_reference']);
            $table->dropColumn('batch_reference');
        });
    }
};
