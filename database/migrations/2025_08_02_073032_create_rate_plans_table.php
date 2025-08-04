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
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->onDelete('cascade');
            $table->json('name'); // translatable field
            $table->boolean('refundable')->default(true);
            $table->boolean('breakfast_included')->default(false);
            $table->json('cancellation_policy')->nullable();
            $table->boolean('deposit_required')->default(false);
            $table->decimal('deposit_percentage', 5, 2)->nullable(); // percentage of total booking
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['room_type_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
