<?php

namespace Database\Seeders;

use App\Models\Resort;
use App\Models\Amenity;
use Illuminate\Database\Seeder;

class ResortsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Maldives specific resorts
        $maldivesResorts = [
            [
                'name' => 'Paradise Island Resort & Spa',
                'location' => 'North Male Atoll',
                'island' => 'Lankanfinolhu',
                'atoll' => 'North Male Atoll',
                'coordinates' => '4.2817,73.5213',
                'contact_email' => 'info@paradiseislandmaldives.com',
                'contact_phone' => '+960-664-0011',
                'star_rating' => 5,
                'resort_type' => 'resort',
            ],
            [
                'name' => 'Sun Island Resort & Spa',
                'location' => 'South Ari Atoll',
                'island' => 'Nalaguraidhoo',
                'atoll' => 'South Ari Atoll',
                'coordinates' => '3.4855,72.8757',
                'contact_email' => 'info@sunislandmaldives.com',
                'contact_phone' => '+960-668-0088',
                'star_rating' => 5,
                'resort_type' => 'resort',
            ],
            [
                'name' => 'Bandos Maldives',
                'location' => 'North Male Atoll',
                'island' => 'Bandos',
                'atoll' => 'North Male Atoll',
                'coordinates' => '4.2673,73.4942',
                'contact_email' => 'bandos@bandos.com.mv',
                'contact_phone' => '+960-664-0088',
                'star_rating' => 4,
                'resort_type' => 'resort',
            ],
            [
                'name' => 'Kurumba Maldives',
                'location' => 'North Male Atoll',
                'island' => 'Vihamanaafushi',
                'atoll' => 'North Male Atoll',
                'coordinates' => '4.2232,73.5170',
                'contact_email' => 'kurumba@kurumba.com',
                'contact_phone' => '+960-664-2324',
                'star_rating' => 5,
                'resort_type' => 'resort',
            ],
            [
                'name' => 'Velassaru Maldives',
                'location' => 'South Male Atoll',
                'island' => 'Velassaru',
                'atoll' => 'South Male Atoll',
                'coordinates' => '4.1174,73.4367',
                'contact_email' => 'reservations@velassaru.com',
                'contact_phone' => '+960-665-6100',
                'star_rating' => 5,
                'resort_type' => 'resort',
            ],
        ];

        // Get all amenities
        $amenities = Amenity::all();
        $resortAmenities = $amenities->where('category', '!=', 'comfort')
                                    ->where('category', '!=', 'view')
                                    ->where('category', '!=', 'security');

        foreach ($maldivesResorts as $resortData) {
            $resort = new Resort();
            $resort->name = $resortData['name'];
            $resort->slug = \Illuminate\Support\Str::slug($resortData['name']);
            $resort->location = $resortData['location'];
            $resort->island = $resortData['island'];
            $resort->atoll = $resortData['atoll'];
            $resort->coordinates = $resortData['coordinates'];
            $resort->contact_email = $resortData['contact_email'];
            $resort->contact_phone = $resortData['contact_phone'];
            $resort->description = [
                'en' => 'Experience the ultimate tropical paradise at ' . $resortData['name'] . '. Nestled in the stunning ' . $resortData['atoll'] . ' of the Maldives, our resort offers pristine beaches, crystal-clear waters, and world-class amenities. Perfect for honeymoons, family vacations, or a luxurious getaway, our resort promises an unforgettable experience in one of the most beautiful destinations on Earth. Enjoy water sports, spa treatments, fine dining, and more in this idyllic setting surrounded by the Indian Ocean.'
            ];
            $resort->star_rating = $resortData['star_rating'];
            $resort->resort_type = $resortData['resort_type'];
            $resort->check_in_time = '14:00:00';
            $resort->check_out_time = '12:00:00';
            $resort->tax_rules = [
                'gst' => 12,
                'service_fee' => 10
            ];
            $resort->currency = 'USD';
            $resort->active = true;
            $resort->save();

            // Attach 5-10 random amenities to each resort
            $resort->amenities()->attach(
                $resortAmenities->random(rand(5, 10))->pluck('id')->toArray()
            );
        }

        // Create additional random resorts
        Resort::factory()
            ->count(5)
            ->create()
            ->each(function ($resort) use ($resortAmenities) {
                // Attach 5-8 random amenities to each resort
                $resort->amenities()->attach(
                    $resortAmenities->random(rand(5, 8))->pluck('id')->toArray()
                );
            });
    }
}
