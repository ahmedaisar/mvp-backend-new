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
        // Update resorts table currency default from MVR to USD
        Schema::table('resorts', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->change();
        });

        // Update existing resorts to use USD currency
        DB::table('resorts')->update(['currency' => 'USD']);

        // Rename MVR-specific columns in bookings table to USD
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('subtotal_mvr', 'subtotal_usd');
            $table->renameColumn('total_price_usd', 'total_price_usd'); 
        });

        // Update booking_items currency default from MVR to USD
        Schema::table('booking_items', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->change();
        });

        // Update existing booking items to use USD currency
        DB::table('booking_items')->update(['currency' => 'USD']);

        // Update payment gateway enum to replace local_mvr with local_usd
        // Schema::table('transactions', function (Blueprint $table) {
        //     $table->enum('payment_gateway', ['stripe', 'local_usd', 'bank_transfer'])->change();
        // });

        // Update existing transactions that used local_mvr to local_usd
        DB::table('transactions')
            ->where('payment_gateway', 'local_mvr')
            ->update(['payment_gateway' => 'local_usd']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert resorts table currency default from USD to MVR
        Schema::table('resorts', function (Blueprint $table) {
            $table->string('currency', 3)->default('MVR')->change();
        });

        DB::table('resorts')->update(['currency' => 'MVR']);

        // Rename USD columns back to MVR in bookings table
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('subtotal_usd', 'subtotal_mvr');
            $table->renameColumn('total_price_usd', 'total_price_usd');
        });

        // Revert booking_items currency default from USD to MVR
        Schema::table('booking_items', function (Blueprint $table) {
            $table->string('currency', 3)->default('MVR')->change();
        });

        DB::table('booking_items')->update(['currency' => 'MVR']);

        // Revert payment gateway enum to replace local_usd with local_mvr
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('payment_gateway', ['stripe', 'local_mvr', 'bank_transfer'])->change();
        });

        DB::table('transactions')
            ->where('payment_gateway', 'local_usd')
            ->update(['payment_gateway' => 'local_mvr']);
    }
};
