<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE permission_requests MODIFY COLUMN request_type ENUM('delegate','temporary','permanent') NOT NULL DEFAULT 'delegate'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE permission_requests MODIFY COLUMN request_type ENUM('temporary','permanent') NOT NULL DEFAULT 'permanent'");
    }
};
