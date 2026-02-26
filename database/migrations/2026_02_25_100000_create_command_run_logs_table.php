<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * One row per command slug; each run overwrites that row (last run only).
     */
    public function up(): void
    {
        Schema::create('command_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique()->comment('Unique command identifier e.g. refresh-new, refresh-orders');
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_completed_at')->nullable();
            $table->unsignedInteger('total_processed')->default(0);
            $table->unsignedInteger('processed_ok')->default(0);
            $table->unsignedInteger('processed_failed')->default(0);
            $table->string('status', 32)->default('running')->comment('running, completed, failed, cancelled');
            $table->text('last_note')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('last_started_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_run_logs');
    }
};
