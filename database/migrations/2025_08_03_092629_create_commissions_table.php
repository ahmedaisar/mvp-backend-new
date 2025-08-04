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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['agency', 'affiliate', 'corporate']);
            $table->string('agent_code')->unique();
            $table->string('contact_email');
            $table->string('contact_name');
            $table->string('contact_phone')->nullable();
            $table->enum('commission_type', ['percentage', 'fixed_amount']);
            $table->decimal('commission_rate', 5, 2); // 5 digits, 2 decimal places (e.g., 12.50 for 12.5%)
            $table->decimal('fixed_amount', 10, 2)->nullable(); // For fixed commission amounts
            $table->json('applicable_resorts')->nullable(); // Array of resort IDs
            $table->json('applicable_room_types')->nullable(); // Array of room type IDs
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->decimal('minimum_booking_value', 10, 2)->default(0);
            $table->integer('minimum_nights')->default(1);
            $table->enum('payment_frequency', ['per_booking', 'monthly', 'quarterly']);
            $table->json('terms_and_conditions')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['agent_code', 'active']);
            $table->index(['type', 'active']);
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
