<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Make stock_id nullable (avoids doctrine/dbal dependency)
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE parts_purchases MODIFY stock_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('parts_purchases', function (Blueprint $table) {
                $table->unsignedBigInteger('stock_id')->nullable()->change();
            });
        }

        Schema::table('parts_purchases', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('stock_id')->constrained('parts_purchase_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parts_purchases', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE parts_purchases MODIFY stock_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('parts_purchases', function (Blueprint $table) {
                $table->unsignedBigInteger('stock_id')->nullable(false)->change();
            });
        }
    }
};
