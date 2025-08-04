<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RatePlan>
 */
class RatePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Maldives specific rate plans
        $ratePlans = [
            'Standard Rate' => [
                'refundable' => true,
                'breakfast' => true,
                'deposit' => false,
                'deposit_pct' => 0
            ],
            'Non-Refundable' => [
                'refundable' => false,
                'breakfast' => true,
                'deposit' => false,
                'deposit_pct' => 0
            ],
            'All Inclusive' => [
                'refundable' => true,
                'breakfast' => true,
                'deposit' => true,
                'deposit_pct' => 20
            ],
            'Half Board' => [
                'refundable' => true,
                'breakfast' => true,
                'deposit' => false,
                'deposit_pct' => 0
            ],
            'Full Board' => [
                'refundable' => true,
                'breakfast' => true,
                'deposit' => true,
                'deposit_pct' => 15
            ],
            'Advanced Purchase' => [
                'refundable' => false,
                'breakfast' => true,
                'deposit' => true,
                'deposit_pct' => 100
            ],
            'Honeymoon Package' => [
                'refundable' => true,
                'breakfast' => true,
                'deposit' => true,
                'deposit_pct' => 30
            ],
            'Family Package' => [
                'refundable' => true,
                'breakfast' => true,
                'deposit' => true,
                'deposit_pct' => 20
            ],
        ];
        
        $planName = $this->faker->randomElement(array_keys($ratePlans));
        $details = $ratePlans[$planName];
        
        // Set country restrictions
        $restrictionType = $this->faker->randomElement(['none', 'include_only', 'exclude_only']);
        
        // Common tourist countries visiting Maldives
        $popularCountries = ['US', 'GB', 'DE', 'CN', 'IN', 'RU', 'IT', 'FR', 'JP', 'AE', 'AU', 'CA', 'KR', 'SG'];
        
        // Randomly select 3-6 countries
        $selectedCountries = $this->faker->randomElements(
            $popularCountries,
            $this->faker->numberBetween(3, 6)
        );
        
        return [
            'room_type_id' => RoomType::factory(),
            'name' => [
                'en' => $planName,
            ],
            'refundable' => $details['refundable'],
            'breakfast_included' => $details['breakfast'],
            'cancellation_policy' => [
                'en' => $details['refundable'] 
                    ? 'Free cancellation up to 7 days before arrival. Late cancellation or no-show will incur a penalty equal to 1 night stay.'
                    : 'This rate is non-refundable and cannot be changed or cancelled. No refund for no-show or early check-out.'
            ],
            'deposit_required' => $details['deposit'],
            'deposit_percentage' => $details['deposit_pct'],
            'active' => $this->faker->boolean(90), // 90% chance of being active
            'applicable_countries' => $restrictionType === 'include_only' ? $selectedCountries : null,
            'excluded_countries' => $restrictionType === 'exclude_only' ? $selectedCountries : null,
            'country_restriction_type' => $restrictionType,
        ];
    }
}
