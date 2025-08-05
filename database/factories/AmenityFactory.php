<?php

namespace Database\Factories;

use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        
        return [
            'name' => $name,
            'code' => Str::slug($name),
            'icon' => $this->faker->randomElement(['wifi', 'pool', 'spa', 'gym', 'restaurant', 'bar']),
            'category' => $this->faker->randomElement(['general', 'room', 'outdoor', 'service']),
            'active' => true,
        ];
    }
    
    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
