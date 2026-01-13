<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultFormulaToVariationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('variations', function (Blueprint $table) {
            $table->json('default_stock_formula')->nullable()->after('listed_stock')->comment('Per-variation default stock formula: {"value": float, "type": "percentage|fixed", "apply_to": "pushed|total"}');
            $table->integer('default_min_threshold')->nullable()->after('default_stock_formula')->comment('Per-variation default min threshold');
            $table->integer('default_max_threshold')->nullable()->after('default_min_threshold')->comment('Per-variation default max threshold');
            $table->integer('default_min_stock_required')->nullable()->after('default_max_threshold')->comment('Per-variation default min stock required');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('variations', function (Blueprint $table) {
            $table->dropColumn(['default_stock_formula', 'default_min_threshold', 'default_max_threshold', 'default_min_stock_required']);
        });
    }
}
