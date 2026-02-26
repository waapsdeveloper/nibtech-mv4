<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('part_batches', function (Blueprint $table) {
            $table->string('name_label', 255)->nullable()->after('batch_number')
                ->comment('Name as received in this batch (label only; part name is not updated when SKU exists)');
        });
    }

    public function down(): void
    {
        Schema::table('part_batches', function (Blueprint $table) {
            $table->dropColumn('name_label');
        });
    }
};
