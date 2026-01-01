<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('listing_marketplace_history', function (Blueprint $table) {
            $table->json('row_snapshot')->nullable()->after('new_value')->comment('Full row snapshot as JSON when change was recorded');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('listing_marketplace_history', function (Blueprint $table) {
            $table->dropColumn('row_snapshot');
        });
    }
};
