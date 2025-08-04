<?php

namespace Database\Factories;

use App\Models\Resort;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Maldives specific room types
        $roomTypes = [
            'Beach Villa' => ['code' => 'BV', 'min_price' => 300, 'max_price' => 500, 'adults' => 2, 'children' => 1],
            'Water Villa' => ['code' => 'WV', 'min_price' => 400, 'max_price' => 700, 'adults' => 2, 'children' => 1],
            'Pool Villa' => ['code' => 'PV', 'min_price' => 500, 'max_price' => 800, 'adults' => 2, 'children' => 2],
            'Family Beach Villa' => ['code' => 'FBV', 'min_price' => 600, 'max_price' => 900, 'adults' => 4, 'children' => 2],
            'Sunset Water Villa' => ['code' => 'SWV', 'min_price' => 700, 'max_price' => 1200, 'adults' => 2, 'children' => 2],
            'Deluxe Water Villa' => ['code' => 'DWV', 'min_price' => 800, 'max_price' => 1500, 'adults' => 2, 'children' => 2],
            'Presidential Suite' => ['code' => 'PS', 'min_price' => 1500, 'max_price' => 3000, 'adults' => 4, 'children' => 4],
            'Overwater Bungalow' => ['code' => 'OWB', 'min_price' => 600, 'max_price' => 1000, 'adults' => 2, 'children' => 1],
            'Garden Villa' => ['code' => 'GV', 'min_price' => 250, 'max_price' => 400, 'adults' => 2, 'children' => 1],
            'Honeymoon Villa' => ['code' => 'HV', 'min_price' => 700, 'max_price' => 1200, 'adults' => 2, 'children' => 0]
        ];
        
        $roomType = $this->faker->randomElement(array_keys($roomTypes));
        $details = $roomTypes[$roomType];
        
        return [
            'resort_id' => Resort::factory(),
            'code' => $details['code'],
            'name' => [
                'en' => $roomType,
                'ru' => $roomType, // For simplicity, using same name
                'fr' => $roomType, // For simplicity, using same name
            ],
            'capacity_adults' => $details['adults'],
            'capacity_children' => $details['children'],
            'default_price' => $this->faker->numberBetween($details['min_price'], $details['max_price']),
            'images' => [],
            'active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }
}
