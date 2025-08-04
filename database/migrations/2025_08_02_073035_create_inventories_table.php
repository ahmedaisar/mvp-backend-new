<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_plan_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('available_rooms')->unsigned()->default(0);
            $table->boolean('blocked')->default(false);
            $table->timestamps();
            
            $table->unique(['rate_plan_id', 'date']);
            $table->index(['date', 'rate_plan_id']);
            $table->index(['date', 'available_rooms']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
