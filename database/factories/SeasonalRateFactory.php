<?php

namespace Database\Factories;

use App\Models\RatePlan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SeasonalRate>
 */
class SeasonalRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Maldives has two main seasons - dry season (Dec-Apr) and rainy season (May-Nov)
        // Prices are higher during dry season (high season)
        
        // Get the rate plan to base pricing on
        $ratePlan = RatePlan::factory()->create();
        $roomType = $ratePlan->roomType;
        
        // Get base price from room type
        $basePrice = $roomType->default_price;
        
        // Define Maldives seasonal periods
        $seasons = [
            'High Season' => [
                'start' => Carbon::create(2025, 12, 1),
                'end' => Carbon::create(2026, 4, 30),
                'min_price_factor' => 1.2,
                'max_price_factor' => 1.5,
                'min_stay' => 3,
                'max_stay' => 30
            ],
            'Peak Season' => [
                'start' => Carbon::create(2025, 12, 20),
                'end' => Carbon::create(2026, 1, 10),
                'min_price_factor' => 1.8,
                'max_price_factor' => 2.2,
                'min_stay' => 5,
                'max_stay' => 30
            ],
            'Shoulder Season' => [
                'start' => Carbon::create(2025, 5, 1),
                'end' => Carbon::create(2025, 7, 31),
                'min_price_factor' => 0.8,
                'max_price_factor' => 1.0,
                'min_stay' => 2,
                'max_stay' => 30
            ],
            'Low Season' => [
                'start' => Carbon::create(2025, 8, 1),
                'end' => Carbon::create(2025, 11, 30),
                'min_price_factor' => 0.6,
                'max_price_factor' => 0.8,
                'min_stay' => 1,
                'max_stay' => 30
            ],
        ];
        
        $seasonName = $this->faker->randomElement(array_keys($seasons));
        $seasonDetails = $seasons[$seasonName];
        
        // Calculate price factor based on season
        $priceFactor = $this->faker->randomFloat(
            2, 
            $seasonDetails['min_price_factor'], 
            $seasonDetails['max_price_factor']
        );
        
        // Calculate nightly price
        $nightlyPrice = round($basePrice * $priceFactor, 2);
        
        return [
            'rate_plan_id' => $ratePlan->id,
            'start_date' => $seasonDetails['start'],
            'end_date' => $seasonDetails['end'],
            'nightly_price' => $nightlyPrice,
            'min_stay' => $seasonDetails['min_stay'],
            'max_stay' => $seasonDetails['max_stay'],
        ];
    }
}
