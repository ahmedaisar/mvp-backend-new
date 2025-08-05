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
            // Drop the problematic unique constraint that only includes rate_plan_id
            $table->dropUnique('inventories_rate_plan_id_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            // Re-add the constraint if rolling back (though this might cause issues)
            $table->unique(['rate_plan_id'], 'inventories_rate_plan_id_date_unique');
        });
    }
};
