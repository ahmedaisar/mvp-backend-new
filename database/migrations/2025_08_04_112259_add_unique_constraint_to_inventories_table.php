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
        Schema::table('inventories', function (Blueprint $table) {
            // Add a unique constraint to ensure no overlapping date ranges for the same rate plan
            $table->unique(['rate_plan_id', 'start_date', 'end_date'], 'inventories_rate_plan_date_range_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inventories_rate_plan_date_range_unique');
        });
    }
};
