<?php

namespace Database\Seeders;

use App\Models\GuestProfile;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class GuestProfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        // Use ISO country codes since the DB field is limited to 2 characters
        $countries = ['US', 'GB', 'AU', 'DE', 'FR', 'IT', 'JP', 'CN', 'IN', 'CA', 'RU', 'BR', 'ZA', 'AE', 'SG'];
        
        // Create 25 guest profiles
        for ($i = 0; $i < 25; $i++) {
            GuestProfile::create([
                'email' => $faker->unique()->safeEmail(),
                'full_name' => $faker->name(),
                'phone' => $faker->phoneNumber(),
                'country' => $faker->randomElement($countries),
                'preferences' => [
                    'dietary_restrictions' => $faker->randomElement(['None', 'Vegetarian', 'Vegan', 'Gluten-Free', 'Lactose Intolerant', null]),
                    'room_preferences' => $faker->randomElement(['High Floor', 'Low Floor', 'Near Elevator', 'Away from Elevator', 'Ocean View', null]),
                    'special_occasions' => $faker->randomElement(['Birthday', 'Anniversary', 'Honeymoon', 'Wedding', null]),
                ],
                'date_of_birth' => $faker->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
                'gender' => $faker->randomElement(['male', 'female', 'other']), // Match enum values
            ]);
        }
        
        // Create some VIP guests
        for ($i = 0; $i < 5; $i++) {
            GuestProfile::create([
                'email' => 'vip' . $i . $faker->unique()->safeEmail(),
                'full_name' => $faker->name(),
                'phone' => $faker->phoneNumber(),
                'country' => $faker->randomElement($countries),
                'preferences' => [
                    'dietary_restrictions' => $faker->randomElement(['None', 'Vegetarian', 'Vegan', 'Gluten-Free', 'Lactose Intolerant']),
                    'room_preferences' => $faker->randomElement(['High Floor', 'Low Floor', 'Near Elevator', 'Away from Elevator', 'Ocean View']),
                    'special_occasions' => $faker->randomElement(['Birthday', 'Anniversary', 'Honeymoon', 'Wedding']),
                    'vip_treatment' => true,
                    'preferred_welcome_drink' => $faker->randomElement(['Champagne', 'Fruit Juice', 'Coconut Water', 'Sparkling Water']),
                    'allergies' => $faker->randomElement(['Nuts', 'Seafood', 'None']),
                ],
                'date_of_birth' => $faker->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
                'gender' => $faker->randomElement(['male', 'female', 'other']), // Match enum values
            ]);
        }
    }
}
