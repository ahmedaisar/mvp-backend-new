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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resort_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('code', 50)->unique();
            $table->json('description'); // translatable field
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 8, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('max_uses')->unsigned()->nullable();
            $table->integer('current_uses')->unsigned()->default(0);
            $table->json('applicable_rate_plans')->nullable(); // array of rate plan IDs
            $table->decimal('min_booking_amount', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['code', 'active']);
            $table->index(['start_date', 'end_date', 'active']);
            $table->index(['resort_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
