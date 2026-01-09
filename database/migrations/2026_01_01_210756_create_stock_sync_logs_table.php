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
        Schema::create('stock_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('marketplace_id')->default(1)->comment('Marketplace ID (default: 1 for BackMarket)');
            $table->enum('status', ['running', 'completed', 'failed', 'cancelled'])->default('running');
            $table->integer('total_records')->default(0)->comment('Total records to sync');
            $table->integer('synced_count')->default(0)->comment('Successfully synced count');
            $table->integer('skipped_count')->default(0)->comment('Skipped count');
            $table->integer('error_count')->default(0)->comment('Error count');
            $table->text('error_details')->nullable()->comment('JSON array of error details');
            $table->text('summary')->nullable()->comment('Summary of the sync operation');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable()->comment('Duration in seconds');
            $table->integer('admin_id')->nullable()->comment('Admin who triggered the sync');
            $table->timestamps();
            
            // Indexes
            $table->index('marketplace_id');
            $table->index('status');
            $table->index('started_at');
            $table->index(['marketplace_id', 'status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_sync_logs');
    }
};
