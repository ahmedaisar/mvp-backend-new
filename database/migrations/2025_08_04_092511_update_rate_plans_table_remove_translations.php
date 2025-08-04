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
        // Get data from the rate_plans table
        $ratePlans = DB::table('rate_plans')->get();
        
        // Create temporary columns
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->string('name_temp')->nullable();
            $table->text('cancellation_policy_temp')->nullable();
        });
        
        // Migrate data from JSON to string
        foreach ($ratePlans as $ratePlan) {
            $nameData = json_decode($ratePlan->name, true);
            $cancellationPolicyData = json_decode($ratePlan->cancellation_policy, true);
            
            $name = is_array($nameData) ? ($nameData['en'] ?? '') : $ratePlan->name;
            $cancellationPolicy = is_array($cancellationPolicyData) ? ($cancellationPolicyData['en'] ?? '') : $ratePlan->cancellation_policy;
            
            DB::table('rate_plans')
                ->where('id', $ratePlan->id)
                ->update([
                    'name_temp' => $name,
                    'cancellation_policy_temp' => $cancellationPolicy,
                ]);
        }
        
        // Drop original columns and rename temp columns
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->dropColumn(['name', 'cancellation_policy']);
        });
        
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->renameColumn('name_temp', 'name');
            $table->renameColumn('cancellation_policy_temp', 'cancellation_policy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration (data conversion), 
        // so we don't provide a reliable way to roll back
        Schema::table('rate_plans', function (Blueprint $table) {
            // Convert string columns back to JSON format (default structure)
            $table->json('name')->change();
            $table->json('cancellation_policy')->change();
        });
    }
};
