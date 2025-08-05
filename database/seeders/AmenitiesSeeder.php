<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Amenity;

class AmenitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $amenities = [
            // Resort Facilities
            [
                'code' => 'wifi',
                'name' => 'Free Wi-Fi',
                'icon' => 'fas fa-wifi',
                'category' => 'connectivity',
                'active' => true,
            ],
            [
                'code' => 'pool',
                'name' => 'Swimming Pool',
                'icon' => 'fas fa-swimming-pool',
                'category' => 'recreation',
                'active' => true,
            ],
            [
                'code' => 'spa',
                'name' => 'Spa & Wellness',
                'icon' => 'fas fa-spa',
                'category' => 'wellness',
                'active' => true,
            ],
            [
                'code' => 'restaurant',
                'name' => 'Restaurant',
                'icon' => 'fas fa-utensils',
                'category' => 'dining',
                'active' => true,
            ],
            [
                'code' => 'bar',
                'name' => 'Bar',
                'icon' => 'fas fa-cocktail',
                'category' => 'dining',
                'active' => true,
            ],
            [
                'code' => 'gym',
                'name' => 'Fitness Center',
                'icon' => 'fas fa-dumbbell',
                'category' => 'recreation',
                'active' => true,
            ],
            [
                'code' => 'diving',
                'name' => 'Diving Center',
                'icon' => 'fas fa-swimmer',
                'category' => 'activities',
                'active' => true,
            ],
            [
                'code' => 'snorkeling',
                'name' => 'Snorkeling',
                'icon' => 'fas fa-mask',
                'category' => 'activities',
                'active' => true,
            ],
            [
                'code' => 'water_sports',
                'name' => 'Water Sports',
                'icon' => 'fas fa-water',
                'category' => 'activities',
                'active' => true,
            ],
            [
                'code' => 'kids_club',
                'name' => 'Kids Club',
                'icon' => 'fas fa-child',
                'category' => 'family',
                'active' => true,
            ],
            
            // Room Amenities
            [
                'code' => 'ac',
                'name' => 'Air Conditioning',
                'icon' => 'fas fa-snowflake',
                'category' => 'comfort',
                'active' => true,
            ],
            [
                'code' => 'minibar',
                'name' => 'Mini Bar',
                'icon' => 'fas fa-wine-bottle',
                'category' => 'comfort',
                'active' => true,
            ],
            [
                'code' => 'balcony',
                'name' => 'Private Balcony',
                'icon' => 'fas fa-building',
                'category' => 'comfort',
                'active' => true,
            ],
            [
                'code' => 'ocean_view',
                'name' => 'Ocean View',
                'icon' => 'fas fa-water',
                'category' => 'view',
                'active' => true,
            ],
            [
                'code' => 'beach_access',
                'name' => 'Direct Beach Access',
                'icon' => 'fas fa-umbrella-beach',
                'category' => 'location',
                'active' => true,
            ],
            [
                'code' => 'coffee_maker',
                'name' => 'Coffee/Tea Maker',
                'icon' => 'fas fa-coffee',
                'category' => 'comfort',
                'active' => true,
            ],
            [
                'code' => 'safe',
                'name' => 'In-room Safe',
                'icon' => 'fas fa-lock',
                'category' => 'security',
                'active' => true,
            ],
            [
                'code' => 'tv',
                'name' => 'Flat Screen TV',
                'icon' => 'fas fa-tv',
                'category' => 'entertainment',
                'active' => true,
            ],
            [
                'code' => 'room_service',
                'name' => '24h Room Service',
                'icon' => 'fas fa-concierge-bell',
                'category' => 'service',
                'active' => true,
            ],
            [
                'code' => 'jacuzzi',
                'name' => 'Private Jacuzzi',
                'icon' => 'fas fa-hot-tub',
                'category' => 'luxury',
                'active' => true,
            ],
        ];

        foreach ($amenities as $amenity) {
            // All amenities should be active
            if (!isset($amenity['active'])) {
                $amenity['active'] = true;
            }
            Amenity::create($amenity);
        }
    }
}
