<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace')) {
            Schema::create('marketplace', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('api_key')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('marketplace', function (Blueprint $table) {
                if (! Schema::hasColumn('marketplace', 'api_key')) {
                    $table->string('api_key')->nullable()->after('name');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace')) {
            return;
        }

        $columns = collect(Schema::getColumnListing('marketplace'));
        $expected = collect(['id', 'name', 'api_key', 'created_at', 'updated_at']);

        if ($columns->diff($expected)->isEmpty() && $expected->diff($columns)->isEmpty()) {
            Schema::dropIfExists('marketplace');
        }
    }
};
