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
        Schema::table('marketplace', function (Blueprint $table) {
            $table->integer('sync_interval_hours')->default(6)->after('status')
                ->comment('Hours between syncs (default 6)');
            $table->time('sync_start_time')->nullable()->after('sync_interval_hours')
                ->comment('Preferred start time for sync (e.g., 00:00, 02:00)');
            $table->boolean('sync_enabled')->default(true)->after('sync_start_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketplace', function (Blueprint $table) {
            $table->dropColumn(['sync_interval_hours', 'sync_start_time', 'sync_enabled']);
        });
    }
};
