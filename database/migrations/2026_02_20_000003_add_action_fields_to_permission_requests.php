<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_requests', function (Blueprint $table) {
            $table->string('action_url')->nullable()->after('note');
            $table->string('action_method', 10)->nullable()->after('action_url');
            $table->text('action_payload')->nullable()->after('action_method');
        });
    }

    public function down(): void
    {
        Schema::table('permission_requests', function (Blueprint $table) {
            $table->dropColumn(['action_url', 'action_method', 'action_payload']);
        });
    }
};
