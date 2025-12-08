<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('context_type', 20);
            $table->unsignedBigInteger('context_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->text('snippet')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'context_type', 'context_id'], 'chat_notifications_context_idx');
            $table->index(['admin_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_notifications');
    }
};
