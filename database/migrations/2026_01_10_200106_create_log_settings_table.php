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
        Schema::create('log_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Setting name/identifier (e.g., care_api_errors, order_sync_warnings)');
            $table->string('channel_name')->comment('Slack channel name (without #)');
            $table->text('webhook_url')->comment('Slack webhook URL for this channel');
            $table->enum('log_level', ['error', 'warning', 'info', 'debug'])->default('info')->comment('Minimum log level to post');
            $table->string('log_type')->comment('Log type/category (e.g., care_api, order_sync, listing_api, stock_sync)');
            $table->json('keywords')->nullable()->comment('Optional keywords to match in log messages (JSON array)');
            $table->boolean('is_enabled')->default(true)->comment('Whether this log setting is active');
            $table->text('description')->nullable()->comment('Description of what logs this setting handles');
            $table->integer('admin_id')->nullable()->comment('Admin who created this setting');
            $table->timestamps();
            
            $table->index(['log_type', 'is_enabled']);
            $table->index('log_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_settings');
    }
};
