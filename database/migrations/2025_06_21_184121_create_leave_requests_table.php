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
        if (Schema::hasTable('leave_requests')) {
            return;
        }

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id'); // Employee
            $table->string('leave_type'); // e.g., annual, sick, casual
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('duration', ['full', 'half', 'short'])->default('full'); // Half-day, etc.
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable(); // Admin approval
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // $table->foreign('admin_id')->references('id')->on('admin');
            // $table->foreign('approved_by')->references('id')->on('admin');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_requests');
    }
};
