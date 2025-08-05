<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\RatePlan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing inventory to avoid conflicts
        DB::table("inventories")->truncate();
        
        // Get all rate plans
        $ratePlans = RatePlan::limit(10)->get();

        // Create inventory for next 365 days for each rate plan
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(365);
        
        foreach ($ratePlans as $ratePlan) {
            $this->command->info("Creating inventory for rate plan: {$ratePlan->id} - {$ratePlan->name}");
            
            // Create inventory entries in 30-day chunks
            $chunkStartDate = $startDate->copy();
            
            while ($chunkStartDate < $endDate) {
                $chunkEndDate = $chunkStartDate->copy()->addDays(29); // Use 29 to avoid overlap
                if ($chunkEndDate > $endDate) {
                    $chunkEndDate = $endDate->copy();
                }
                
                // Create inventory entry for this chunk
                Inventory::create([
                    "rate_plan_id" => $ratePlan->id,
                    "start_date" => $chunkStartDate->format("Y-m-d"),
                    "end_date" => $chunkEndDate->format("Y-m-d"),
                    "available_rooms" => rand(1, 10),
                    "blocked" => false,
                ]);
                
                // Move to next chunk - ensure no overlap
                $chunkStartDate = $chunkEndDate->copy()->addDay();
            }
        }
    }
}
