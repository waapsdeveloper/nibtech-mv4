<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('marketplace_stock', function (Blueprint $table) {
            // Add column to track manual stock adjustments (pushed values)
            $table->integer('manual_adjustment')->default(0)->after('listed_stock')->comment('Manual stock adjustments (add/subtract pushes) - separate from API-synced stock');
            
            // Add index for better query performance
            $table->index('manual_adjustment', 'idx_manual_adjustment');
        });
        
        // Initialize: set all existing manual_adjustment to 0 (no manual adjustments yet)
        DB::statement('UPDATE marketplace_stock SET manual_adjustment = 0 WHERE manual_adjustment IS NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketplace_stock', function (Blueprint $table) {
            $table->dropIndex('idx_manual_adjustment');
            $table->dropColumn('manual_adjustment');
        });
    }
};
