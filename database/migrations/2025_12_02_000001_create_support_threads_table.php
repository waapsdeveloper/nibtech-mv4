<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_threads')) {
            return;
        }

        Schema::create('support_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_id')->nullable();
            $table->string('marketplace_source', 50)->comment('backmarket, refurbed, internal, etc.');
            $table->string('external_thread_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_reference')->nullable()->index();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('status', 50)->default('open');
            $table->string('priority', 50)->nullable();
            $table->boolean('change_of_mind')->default(false);
            $table->timestamp('last_external_activity_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_source', 'external_thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_threads');
    }
};
