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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->string('transaction_id', 100)->unique();
            $table->enum('payment_gateway', ['stripe', 'local_usd', 'bank_transfer']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->enum('type', ['payment', 'refund', 'partial_refund']);
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'cancelled']);
            $table->json('gateway_response')->nullable(); // store full gateway response
            $table->string('gateway_transaction_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['booking_id', 'status']);
            $table->index(['payment_gateway', 'status']);
            $table->index('transaction_id');
            $table->index('gateway_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
