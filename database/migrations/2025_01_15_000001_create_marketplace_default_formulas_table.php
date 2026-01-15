<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketplaceDefaultFormulasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('marketplace_default_formulas', function (Blueprint $table) {
            $table->id();
            $table->integer('marketplace_id');
            $table->json('formula')->nullable()->comment('Default formula: {"value": float, "type": "percentage|fixed", "apply_to": "pushed|total"}');
            $table->integer('min_threshold')->nullable()->comment('Default min threshold');
            $table->integer('max_threshold')->nullable()->comment('Default max threshold');
            $table->integer('min_stock_required')->nullable()->comment('Default min stock required');
            $table->boolean('is_active')->default(true)->comment('Whether this default is active');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('Who created/updated this default');
            $table->text('notes')->nullable()->comment('Optional notes about this default');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('marketplace_id');
            $table->index('is_active');
            
            // Foreign key
            $table->foreign('marketplace_id')->references('id')->on('marketplace')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marketplace_default_formulas');
    }
}
