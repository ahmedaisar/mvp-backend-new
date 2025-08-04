<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SiteSettingsSeeder::class,
            AmenitiesSeeder::class,
            CommunicationTemplatesSeeder::class,
            ResortsSeeder::class,
            RoomTypesSeeder::class,
            RatePlansSeeder::class,
            SeasonalRatesSeeder::class,
        ]);

        // Create admin user
        \App\Models\User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@mvp-grock-ota.com',
            'role' => 'admin',
        ]);

        // Create sample resort manager
        \App\Models\User::factory()->create([
            'name' => 'Resort Manager',
            'email' => 'manager@mvp-grock-ota.com',
            'role' => 'resort_manager',
        ]);
    }
}
