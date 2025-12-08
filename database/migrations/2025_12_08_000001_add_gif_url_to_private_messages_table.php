<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('private_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('private_messages', 'gif_url')) {
                $table->string('gif_url', 2048)->nullable()->after('image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('private_messages', function (Blueprint $table) {
            if (Schema::hasColumn('private_messages', 'gif_url')) {
                $table->dropColumn('gif_url');
            }
        });
    }
};
