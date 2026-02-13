<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('permission');
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->enum('request_type', ['temporary', 'permanent'])->default('permanent');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'permission', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_requests');
    }
};
