<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Resort>
 */
class ResortFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company() . ' Resort';
        
        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'location' => $this->faker->address(),
            'island' => $this->faker->randomElement([
                'Maafushi', 'Thulusdhoo', 'Gulhi', 'Himmafushi', 'Huraa',
                'Dhiffushi', 'Gaafaru', 'Kaashidhoo', 'Fulidhoo', 'Felidhoo'
            ]),
            'atoll' => $this->faker->randomElement([
                'North Male Atoll', 'South Male Atoll', 'Ari Atoll', 'Baa Atoll',
                'Raa Atoll', 'Lhaviyani Atoll', 'Kaafu Atoll', 'Alifu Dhaalu Atoll'
            ]),
            'coordinates' => $this->faker->latitude(3.0, 7.0) . ',' . $this->faker->longitude(72.0, 74.0),
            'contact_email' => $this->faker->companyEmail(),
            'contact_phone' => '+960-' . $this->faker->numerify('###-####'),
            'description' => [
                'en' => $this->faker->paragraphs(3, true)
            ],
            'star_rating' => $this->faker->numberBetween(3, 5),
            'tax_rules' => [
                'gst' => $this->faker->numberBetween(8, 12),
                'service_fee' => $this->faker->numberBetween(10, 15)
            ],
            'currency' => 'MVR',
            'resort_type' => $this->faker->randomElement(['resort', 'hotel', 'villa', 'guesthouse']),
            'check_in_time' => $this->faker->randomElement(['14:00:00', '15:00:00', '16:00:00']),
            'check_out_time' => $this->faker->randomElement(['11:00:00', '12:00:00', '13:00:00']),
            'featured_image' => null,
            'gallery' => [],
            'active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }
}
