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
        // First add the new indices
        Schema::table('inventories', function (Blueprint $table) {
            // Add new indices based on date range columns
            $table->index(['start_date', 'rate_plan_id'], 'inventories_start_date_rate_plan_index');
            $table->index(['end_date', 'rate_plan_id'], 'inventories_end_date_rate_plan_index');
            $table->index(['start_date', 'end_date', 'available_rooms'], 'inventories_date_range_availability_index');
        });
        
        // No need to drop the old indices since they were already handled during the column changes
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            // Drop new indices
            $table->dropIndex('inventories_start_date_rate_plan_index');
            $table->dropIndex('inventories_end_date_rate_plan_index');
            $table->dropIndex('inventories_date_range_availability_index');
        });
    }
};
