<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Set orders.reference = parts purchase slug for existing Parts Batch Receive
     * orders that do not already have the slug, so they are identifiable by slug.
     */
    public function up(): void
    {
        $slug = config('parts.purchase_order_reference_slug', 'parts-purchase');
        $partsOrderTypeId = (int) DB::table('multi_type')
            ->where('table_name', 'orders')
            ->where('name', 'Parts Batch Receive')
            ->value('id');

        if (! $partsOrderTypeId) {
            return;
        }

        DB::table('orders')
            ->where('order_type_id', $partsOrderTypeId)
            ->where(function ($q) use ($slug) {
                $q->whereNull('reference')->orWhere('reference', '!=', $slug);
            })
            ->update(['reference' => $slug]);
    }

    public function down(): void
    {
        // Do not clear reference on rollback; we cannot know which were backfilled.
    }
};
