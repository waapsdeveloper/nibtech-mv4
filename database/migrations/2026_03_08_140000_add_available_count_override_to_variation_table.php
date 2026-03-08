<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When set, listing card "Available" shows this (stocks table as source of truth).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('variation', function (Blueprint $table) {
            $table->unsignedInteger('available_count_override')->nullable()
                ->comment('When set, card Available shows this (stocks table count); null = use relation count');
        });
    }

    public function down(): void
    {
        Schema::table('variation', function (Blueprint $table) {
            $table->dropColumn('available_count_override');
        });
    }
};
