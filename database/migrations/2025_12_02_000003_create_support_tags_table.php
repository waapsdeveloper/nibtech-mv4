<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_tags')) {
            Schema::create('support_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });
        }

        if (!Schema::hasTable('support_tag_thread')) {
            Schema::create('support_tag_thread', function (Blueprint $table) {
            $table->foreignId('support_tag_id')->constrained('support_tags')->cascadeOnDelete();
            $table->foreignId('support_thread_id')->constrained('support_threads')->cascadeOnDelete();
            $table->primary(['support_tag_id', 'support_thread_id']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tag_thread');
        Schema::dropIfExists('support_tags');
    }
};
