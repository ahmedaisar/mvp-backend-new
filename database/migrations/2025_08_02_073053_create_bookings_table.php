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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference', 10)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('guest_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('resort_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('rate_plan_id')->constrained()->onDelete('cascade');
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('nights')->unsigned();
            $table->tinyInteger('adults')->unsigned()->default(2);
            $table->tinyInteger('children')->unsigned()->default(0);
            $table->decimal('subtotal_usd', 12, 2)->default(0); // room cost in USD
            $table->decimal('total_price_usd', 12, 2)->default(0); // total including taxes/fees in USD
            $table->decimal('currency_rate_usd', 8, 4)->default(1); // USD exchange rate
            $table->foreignId('promotion_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->foreignId('transfer_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])->default('pending');
            $table->json('special_requests')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['status', 'check_in']);
            $table->index(['resort_id', 'check_in', 'check_out']);
            $table->index(['guest_profile_id', 'status']);
            $table->index('booking_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
