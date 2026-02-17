<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('part_broken_records', function (Blueprint $table) {
            $table->string('responsible_person', 255)->nullable()->after('notes')
                ->comment('Person who broke the part or who received it already broken');
        });
    }

    public function down(): void
    {
        Schema::table('part_broken_records', function (Blueprint $table) {
            $table->dropColumn('responsible_person');
        });
    }
};
