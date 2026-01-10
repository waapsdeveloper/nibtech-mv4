<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_messages')) {
            return;
        }

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_thread_id')->constrained('support_threads')->cascadeOnDelete();
            $table->string('direction', 20)->default('inbound');
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('attachments')->nullable();
            $table->string('external_message_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->boolean('is_internal_note')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['support_thread_id', 'external_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
