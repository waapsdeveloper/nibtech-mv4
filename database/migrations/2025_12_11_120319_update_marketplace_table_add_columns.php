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
        Schema::table('marketplace', function (Blueprint $table) {
            // Add description column if it doesn't exist
            if (!Schema::hasColumn('marketplace', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            
            // Add status column if it doesn't exist
            if (!Schema::hasColumn('marketplace', 'status')) {
                $table->tinyInteger('status')->default(1)->after('description');
            }
            
            // Add api_secret column if it doesn't exist
            if (!Schema::hasColumn('marketplace', 'api_secret')) {
                $table->text('api_secret')->nullable()->after('api_key');
            }
            
            // Add api_url column if it doesn't exist
            if (!Schema::hasColumn('marketplace', 'api_url')) {
                $table->string('api_url')->nullable()->after('api_secret');
            }
            
            // Add timestamps if they don't exist (add at the end)
            if (!Schema::hasColumn('marketplace', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('marketplace', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketplace', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('marketplace', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('marketplace', 'api_secret')) {
                $table->dropColumn('api_secret');
            }
            if (Schema::hasColumn('marketplace', 'api_url')) {
                $table->dropColumn('api_url');
            }
        });
    }
};
