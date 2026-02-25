<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Marketplace sync failures feature removed - drop table.
     */
    public function up(): void
    {
        Schema::dropIfExists('marketplace_sync_failures');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('marketplace_sync_failures')) {
            return;
        }

        Schema::create('marketplace_sync_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id');
            $table->string('sku', 255);
            $table->unsignedInteger('marketplace_id');
            $table->text('error_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_posted_on_marketplace')->default(false);
            $table->integer('failure_count')->default(1);
            $table->timestamp('first_failed_at')->useCurrent();
            $table->timestamp('last_attempted_at')->useCurrent();
            $table->timestamps();
            $table->unique(['sku', 'marketplace_id'], 'unique_sku_marketplace');
            $table->index('variation_id');
            $table->index('marketplace_id');
            $table->index('is_posted_on_marketplace');
            $table->index('last_attempted_at');
        });
    }
};
