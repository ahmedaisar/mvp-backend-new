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
                'name' => [
                    'en' => 'Free Wi-Fi',
                    'ru' => 'Бесплатный Wi-Fi',
                    'fr' => 'Wi-Fi gratuit',
                ],
                'icon' => 'fas fa-wifi',
                'category' => 'connectivity',
                'active' => true,
            ],
            [
                'code' => 'pool',
                'name' => [
                    'en' => 'Swimming Pool',
                    'ru' => 'Бассейн',
                    'fr' => 'Piscine',
                ],
                'icon' => 'fas fa-swimming-pool',
                'category' => 'recreation',
            ],
            [
                'code' => 'spa',
                'name' => [
                    'en' => 'Spa & Wellness',
                    'ru' => 'Спа и оздоровление',
                    'fr' => 'Spa et bien-être',
                ],
                'icon' => 'fas fa-spa',
                'category' => 'wellness',
            ],
            [
                'code' => 'restaurant',
                'name' => [
                    'en' => 'Restaurant',
                    'ru' => 'Ресторан',
                    'fr' => 'Restaurant',
                ],
                'icon' => 'fas fa-utensils',
                'category' => 'dining',
            ],
            [
                'code' => 'bar',
                'name' => [
                    'en' => 'Bar',
                    'ru' => 'Бар',
                    'fr' => 'Bar',
                ],
                'icon' => 'fas fa-cocktail',
                'category' => 'dining',
            ],
            [
                'code' => 'gym',
                'name' => [
                    'en' => 'Fitness Center',
                    'ru' => 'Фитнес-центр',
                    'fr' => 'Centre de fitness',
                ],
                'icon' => 'fas fa-dumbbell',
                'category' => 'recreation',
            ],
            [
                'code' => 'diving',
                'name' => [
                    'en' => 'Diving Center',
                    'ru' => 'Дайвинг-центр',
                    'fr' => 'Centre de plongée',
                ],
                'icon' => 'fas fa-swimmer',
                'category' => 'activities',
            ],
            [
                'code' => 'snorkeling',
                'name' => [
                    'en' => 'Snorkeling',
                    'ru' => 'Сноркелинг',
                    'fr' => 'Plongée avec tuba',
                ],
                'icon' => 'fas fa-mask',
                'category' => 'activities',
            ],
            [
                'code' => 'water_sports',
                'name' => [
                    'en' => 'Water Sports',
                    'ru' => 'Водные виды спорта',
                    'fr' => 'Sports nautiques',
                ],
                'icon' => 'fas fa-water',
                'category' => 'activities',
            ],
            [
                'code' => 'kids_club',
                'name' => [
                    'en' => 'Kids Club',
                    'ru' => 'Детский клуб',
                    'fr' => 'Club enfants',
                ],
                'icon' => 'fas fa-child',
                'category' => 'family',
            ],
            
            // Room Amenities
            [
                'code' => 'ac',
                'name' => [
                    'en' => 'Air Conditioning',
                    'ru' => 'Кондиционер',
                    'fr' => 'Climatisation',
                ],
                'icon' => 'fas fa-snowflake',
                'category' => 'comfort',
            ],
            [
                'code' => 'minibar',
                'name' => [
                    'en' => 'Mini Bar',
                    'ru' => 'Мини-бар',
                    'fr' => 'Mini-bar',
                ],
                'icon' => 'fas fa-wine-bottle',
                'category' => 'comfort',
            ],
            [
                'code' => 'balcony',
                'name' => [
                    'en' => 'Private Balcony',
                    'ru' => 'Частный балкон',
                    'fr' => 'Balcon privé',
                ],
                'icon' => 'fas fa-building',
                'category' => 'comfort',
            ],
            [
                'code' => 'ocean_view',
                'name' => [
                    'en' => 'Ocean View',
                    'ru' => 'Вид на океан',
                    'fr' => 'Vue sur océan',
                ],
                'icon' => 'fas fa-water',
                'category' => 'view',
            ],
            [
                'code' => 'beach_access',
                'name' => [
                    'en' => 'Direct Beach Access',
                    'ru' => 'Прямой доступ к пляжу',
                    'fr' => 'Accès direct à la plage',
                ],
                'icon' => 'fas fa-umbrella-beach',
                'category' => 'location',
            ],
            [
                'code' => 'coffee_maker',
                'name' => [
                    'en' => 'Coffee/Tea Maker',
                    'ru' => 'Кофеварка/чайник',
                    'fr' => 'Machine à café/thé',
                ],
                'icon' => 'fas fa-coffee',
                'category' => 'comfort',
            ],
            [
                'code' => 'safe',
                'name' => [
                    'en' => 'In-room Safe',
                    'ru' => 'Сейф в номере',
                    'fr' => 'Coffre-fort',
                ],
                'icon' => 'fas fa-lock',
                'category' => 'security',
            ],
            [
                'code' => 'tv',
                'name' => [
                    'en' => 'Flat Screen TV',
                    'ru' => 'Плоский телевизор',
                    'fr' => 'TV écran plat',
                ],
                'icon' => 'fas fa-tv',
                'category' => 'entertainment',
            ],
            [
                'code' => 'room_service',
                'name' => [
                    'en' => '24h Room Service',
                    'ru' => 'Круглосуточное обслуживание номеров',
                    'fr' => 'Service chambre 24h',
                ],
                'icon' => 'fas fa-concierge-bell',
                'category' => 'service',
            ],
            [
                'code' => 'jacuzzi',
                'name' => [
                    'en' => 'Private Jacuzzi',
                    'ru' => 'Частное джакузи',
                    'fr' => 'Jacuzzi privé',
                ],
                'icon' => 'fas fa-hot-tub',
                'category' => 'luxury',
            ],
        ];

        foreach ($amenities as $amenity) {
            // Ensure all amenities are active by default
            $amenity['active'] = true;
            Amenity::create($amenity);
        }
    }
}
