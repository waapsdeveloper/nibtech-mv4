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
        Schema::table('admin', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('leave_policy_id')->nullable();
            $table->string('pay_type_id')->nullable(); // hourly, daily, monthly, etc.
            $table->decimal('salary', 12, 2)->nullable();
            $table->integer('annual_leave_quota')->default(0);
            $table->integer('leaves_taken')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admin', function (Blueprint $table) {
            //
            $table->dropColumn([
                'shift_id',
                'leave_policy_id',
                'salary', 'pay_type_id'
            ]);
        });
    }
};
