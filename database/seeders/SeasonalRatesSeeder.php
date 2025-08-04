<?php

namespace Database\Seeders;

use App\Models\RatePlan;
use App\Models\SeasonalRate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SeasonalRatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define Maldives seasonal periods for 2025-2026
        $seasons = [
            'High Season' => [
                'start' => Carbon::create(2025, 12, 1),
                'end' => Carbon::create(2026, 4, 30),
                'price_factor' => 1.3,
                'min_stay' => 3,
                'max_stay' => 30
            ],
            'Peak Season' => [
                'start' => Carbon::create(2025, 12, 20),
                'end' => Carbon::create(2026, 1, 10),
                'price_factor' => 2.0,
                'min_stay' => 5,
                'max_stay' => 30
            ],
            'Shoulder Season' => [
                'start' => Carbon::create(2025, 5, 1),
                'end' => Carbon::create(2025, 7, 31),
                'price_factor' => 0.9,
                'min_stay' => 2,
                'max_stay' => 30
            ],
            'Low Season' => [
                'start' => Carbon::create(2025, 8, 1),
                'end' => Carbon::create(2025, 11, 30),
                'price_factor' => 0.7,
                'min_stay' => 1,
                'max_stay' => 30
            ],
        ];

        // Get all rate plans
        $ratePlans = RatePlan::all();

        foreach ($ratePlans as $ratePlan) {
            // Get room type's default price as base price
            $basePrice = $ratePlan->roomType->default_price;
            
            // Apply a discount/premium based on the rate plan type
            $planName = '';
            
            // Handle different name formats (string or array with translations)
            if (is_array($ratePlan->name)) {
                $planName = $ratePlan->name['en'] ?? '';
            } else {
                $planName = $ratePlan->name;
            }
            
            $planName = strtolower($planName);
            
            if (str_contains($planName, 'non-refundable')) {
                $basePrice *= 0.85; // 15% discount for non-refundable
            } elseif (str_contains($planName, 'all inclusive')) {
                $basePrice *= 1.7; // 70% premium for all-inclusive
            } elseif (str_contains($planName, 'half board')) {
                $basePrice *= 1.3; // 30% premium for half board
            } elseif (str_contains($planName, 'full board')) {
                $basePrice *= 1.5; // 50% premium for full board
            } elseif (str_contains($planName, 'honeymoon')) {
                $basePrice *= 1.6; // 60% premium for honeymoon package
            }
            
            // Create seasonal rates for each season
            foreach ($seasons as $seasonName => $seasonData) {
                // Calculate nightly price based on season
                $nightlyPrice = round($basePrice * $seasonData['price_factor'], 2);
                
                // Create the seasonal rate
                SeasonalRate::create([
                    'rate_plan_id' => $ratePlan->id,
                    'start_date' => $seasonData['start'],
                    'end_date' => $seasonData['end'],
                    'nightly_price' => $nightlyPrice,
                    'min_stay' => $seasonData['min_stay'],
                    'max_stay' => $seasonData['max_stay'],
                ]);
            }
        }
    }
}
