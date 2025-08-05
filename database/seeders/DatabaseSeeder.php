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
            // 1. Base configuration and lookup data
            RolePermissionSeeder::class,
            SiteSettingsSeeder::class,
            AmenitiesSeeder::class,
            CommunicationTemplatesSeeder::class,
            
            // 2. Core business entities
            ResortsSeeder::class,
            RoomTypesSeeder::class,
            RatePlansSeeder::class,
            
            // 3. Pricing and availability
            SeasonalRatesSeeder::class,
            InventorySeeder::class,
            
            // 4. Supporting entities
            PromotionsSeeder::class,
            TransfersSeeder::class,
            GuestProfilesSeeder::class,
            
            // 5. Transactions and bookings (depends on all above)
            BookingsSeeder::class,
            
            // 6. Admin users (should be created after roles)
            AdminUserSeeder::class
        ]);
    }
}
