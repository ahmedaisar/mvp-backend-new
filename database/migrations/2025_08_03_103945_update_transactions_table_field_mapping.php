<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add user_id to track who initiated the transaction
            $table->foreignId('user_id')->nullable()->after('booking_id')->constrained()->onDelete('set null');
            
            // Add payment_method field as an alias/additional field to payment_gateway
            $table->string('payment_method', 50)->nullable()->after('payment_gateway');
            
            // Update status enum to include all values from TransactionResource
            DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'processing', 'success', 'failed', 'cancelled', 'completed', 'refunded', 'partially_refunded') NOT NULL");
            
            // Add reference_number for external payment references
            $table->string('reference_number')->nullable()->after('gateway_transaction_id');
            
            // Add fee tracking
            $table->decimal('fee_amount', 10, 2)->default(0)->after('amount');
            
            // Add description field
            $table->text('description')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'payment_method', 'reference_number', 'fee_amount', 'description'
            ]);
            
            // Revert status enum
            DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'processing', 'success', 'failed', 'cancelled') NOT NULL");
        });
    }
};
