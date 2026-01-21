<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Clean existing duplicates by keeping the smallest id per (order_id, reference_id)
        $duplicates = DB::table('order_items')
            ->select('order_id', 'reference_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('reference_id')
            ->groupBy('order_id', 'reference_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('order_items')
                ->where('order_id', $dup->order_id)
                ->where('reference_id', $dup->reference_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'order_id') || ! Schema::hasColumn('order_items', 'reference_id')) {
                return;
            }

            $table->unique(['order_id', 'reference_id'], 'order_items_order_id_reference_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique('order_items_order_id_reference_id_unique');
        });
    }
};
