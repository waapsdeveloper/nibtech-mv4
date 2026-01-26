<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listing_stock_comparisons', function (Blueprint $table) {
            // Index for lookups by variation and marketplace
            $table->index(['variation_id', 'marketplace_id'], 'idx_variation_marketplace');

            // Index for date-based queries
            $table->index('compared_at', 'idx_compared_at');

            // Composite index for discrepancy queries
            $table->index(['has_discrepancy', 'compared_at'], 'idx_discrepancy');

            // Index for variation SKU lookups
            $table->index('variation_sku', 'idx_variation_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listing_stock_comparisons', function (Blueprint $table) {
            $table->dropIndex('idx_variation_marketplace');
            $table->dropIndex('idx_compared_at');
            $table->dropIndex('idx_discrepancy');
            $table->dropIndex('idx_variation_sku');
        });
    }
};
