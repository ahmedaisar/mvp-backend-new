<?php

namespace Database\Seeders;

use App\Models\Resort;
use App\Models\RoomType;
use App\Models\Amenity;
use Illuminate\Database\Seeder;

class RoomTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Maldives-specific room types for each resort
        $roomTypes = [
            // Common room types for Maldives resorts
            [
                'code' => 'BV',
                'name' => [
                    'en' => 'Beach Villa',
                    'ru' => 'Пляжная вилла',
                    'fr' => 'Villa sur la plage',
                ],
                'capacity_adults' => 2,
                'capacity_children' => 1,
                'default_price' => 350,
            ],
            [
                'code' => 'WV',
                'name' => [
                    'en' => 'Water Villa',
                    'ru' => 'Водная вилла',
                    'fr' => 'Villa sur l\'eau',
                ],
                'capacity_adults' => 2,
                'capacity_children' => 1,
                'default_price' => 450,
            ],
            [
                'code' => 'PV',
                'name' => [
                    'en' => 'Pool Villa',
                    'ru' => 'Вилла с бассейном',
                    'fr' => 'Villa avec piscine',
                ],
                'capacity_adults' => 2,
                'capacity_children' => 2,
                'default_price' => 550,
            ],
            [
                'code' => 'FBV',
                'name' => [
                    'en' => 'Family Beach Villa',
                    'ru' => 'Семейная пляжная вилла',
                    'fr' => 'Villa familiale sur la plage',
                ],
                'capacity_adults' => 4,
                'capacity_children' => 2,
                'default_price' => 650,
            ],
            [
                'code' => 'SWV',
                'name' => [
                    'en' => 'Sunset Water Villa',
                    'ru' => 'Водная вилла с видом на закат',
                    'fr' => 'Villa sur l\'eau avec vue sur le coucher de soleil',
                ],
                'capacity_adults' => 2,
                'capacity_children' => 1,
                'default_price' => 800,
            ],
            [
                'code' => 'OWB',
                'name' => [
                    'en' => 'Overwater Bungalow',
                    'ru' => 'Бунгало над водой',
                    'fr' => 'Bungalow sur pilotis',
                ],
                'capacity_adults' => 2,
                'capacity_children' => 1,
                'default_price' => 700,
            ],
        ];

        // Get room amenities
        $amenities = Amenity::all();
        $roomAmenities = $amenities->whereIn('category', ['comfort', 'view', 'security', 'entertainment', 'luxury']);

        // For each resort, create 3-5 room types
        $resorts = Resort::all();
        
        foreach ($resorts as $resort) {
            // Shuffle room types to get a random selection
            $shuffledRoomTypes = collect($roomTypes)->shuffle();
            
            // Take 3-5 room types for this resort
            $selectedRoomTypes = $shuffledRoomTypes->take(rand(3, 5));
            
            foreach ($selectedRoomTypes as $roomTypeData) {
                $roomType = new RoomType();
                $roomType->resort_id = $resort->id;
                $roomType->code = $roomTypeData['code'];
                $roomType->name = $roomTypeData['name'];
                $roomType->capacity_adults = $roomTypeData['capacity_adults'];
                $roomType->capacity_children = $roomTypeData['capacity_children'];
                
                // Vary the price a bit based on resort star rating
                $priceAdjustment = 1 + (($resort->star_rating - 3) * 0.15); // 15% price increase per star above 3
                $roomType->default_price = round($roomTypeData['default_price'] * $priceAdjustment, 2);
                
                $roomType->active = true;
                $roomType->save();
                
                // Attach 4-7 random amenities to each room type
                $roomType->amenities()->attach(
                    $roomAmenities->random(rand(4, 7))->pluck('id')->toArray()
                );
            }
        }
    }
}
