<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allow negative values in listed_stock and should_be (e.g. variation.listed_stock = -1).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE listing_available_stock_discrepancies MODIFY listed_stock INT NULL COMMENT \'Current variation.listed_stock\'');
            DB::statement('ALTER TABLE listing_available_stock_discrepancies MODIFY should_be INT NULL COMMENT \'Computed: graded stock (grade<6) - process type 22 - pending order items\'');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE listing_available_stock_discrepancies MODIFY listed_stock INT UNSIGNED NULL COMMENT \'Current variation.listed_stock\'');
            DB::statement('ALTER TABLE listing_available_stock_discrepancies MODIFY should_be INT UNSIGNED NULL COMMENT \'Computed: graded stock (grade<6) - process type 22 - pending order items\'');
        }
    }
};
