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
        Schema::table('commissions', function (Blueprint $table) {
            // Update enum values to match CommissionResource options
            DB::statement("ALTER TABLE commissions MODIFY COLUMN type ENUM('travel_agency', 'tour_operator', 'online_booking', 'corporate', 'wholesale', 'affiliate') NOT NULL");
            
            // Update payment_frequency enum to include all options from resource
            DB::statement("ALTER TABLE commissions MODIFY COLUMN payment_frequency ENUM('per_booking', 'monthly', 'quarterly', 'annually') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            // Revert to original enum values
            DB::statement("ALTER TABLE commissions MODIFY COLUMN type ENUM('agency', 'affiliate', 'corporate') NOT NULL");
            DB::statement("ALTER TABLE commissions MODIFY COLUMN payment_frequency ENUM('per_booking', 'monthly', 'quarterly') NOT NULL");
        });
    }
};
