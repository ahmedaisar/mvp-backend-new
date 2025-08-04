<?php

namespace Database\Seeders;

use App\Models\RoomType;
use App\Models\RatePlan;
use Illuminate\Database\Seeder;

class RatePlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Maldives-specific rate plans
        $ratePlans = [
            [
                'name' => [
                    'en' => 'Standard Rate',
                    'ru' => 'Стандартный тариф',
                    'fr' => 'Tarif standard',
                ],
                'refundable' => true,
                'breakfast_included' => true,
                'cancellation_policy' => [
                    'en' => 'Free cancellation up to 7 days before arrival. Late cancellation or no-show will incur a penalty equal to 1 night stay.',
                ],
                'deposit_required' => false,
                'deposit_percentage' => 0,
            ],
            [
                'name' => [
                    'en' => 'Non-Refundable',
                    'ru' => 'Невозвратный тариф',
                    'fr' => 'Non remboursable',
                ],
                'refundable' => false,
                'breakfast_included' => true,
                'cancellation_policy' => [
                    'en' => 'This rate is non-refundable and cannot be changed or cancelled. No refund for no-show or early check-out.',
                ],
                'deposit_required' => false,
                'deposit_percentage' => 0,
            ],
            [
                'name' => [
                    'en' => 'All Inclusive',
                    'ru' => 'Все включено',
                    'fr' => 'Tout compris',
                ],
                'refundable' => true,
                'breakfast_included' => true,
                'cancellation_policy' => [
                    'en' => 'Free cancellation up to 14 days before arrival. Late cancellation or no-show will incur a penalty equal to 2 nights stay.',
                ],
                'deposit_required' => true,
                'deposit_percentage' => 20,
            ],
            [
                'name' => [
                    'en' => 'Half Board',
                    'ru' => 'Полупансион',
                    'fr' => 'Demi-pension',
                ],
                'refundable' => true,
                'breakfast_included' => true,
                'cancellation_policy' => [
                    'en' => 'Free cancellation up to 7 days before arrival. Late cancellation or no-show will incur a penalty equal to 1 night stay.',
                ],
                'deposit_required' => false,
                'deposit_percentage' => 0,
            ],
            [
                'name' => [
                    'en' => 'Full Board',
                    'ru' => 'Полный пансион',
                    'fr' => 'Pension complète',
                ],
                'refundable' => true,
                'breakfast_included' => true,
                'cancellation_policy' => [
                    'en' => 'Free cancellation up to 10 days before arrival. Late cancellation or no-show will incur a penalty equal to 1 night stay.',
                ],
                'deposit_required' => true,
                'deposit_percentage' => 15,
            ],
            [
                'name' => [
                    'en' => 'Honeymoon Package',
                    'ru' => 'Медовый месяц',
                    'fr' => 'Forfait lune de miel',
                ],
                'refundable' => true,
                'breakfast_included' => true,
                'cancellation_policy' => [
                    'en' => 'Free cancellation up to 30 days before arrival. Late cancellation or no-show will incur a penalty equal to 2 nights stay.',
                ],
                'deposit_required' => true,
                'deposit_percentage' => 30,
            ],
        ];

        // Common tourist countries visiting Maldives
        $popularCountries = ['US', 'GB', 'DE', 'CN', 'IN', 'RU', 'IT', 'FR', 'JP', 'AE', 'AU', 'CA', 'KR', 'SG'];

        // For each room type, create 2-4 rate plans
        $roomTypes = RoomType::all();
        
        foreach ($roomTypes as $roomType) {
            // Shuffle rate plans to get a random selection
            $shuffledRatePlans = collect($ratePlans)->shuffle();
            
            // Take 2-4 rate plans for this room type
            $selectedRatePlans = $shuffledRatePlans->take(rand(2, 4));
            
            foreach ($selectedRatePlans as $ratePlanData) {
                $ratePlan = new RatePlan();
                $ratePlan->room_type_id = $roomType->id;
                $ratePlan->name = $ratePlanData['name'];
                $ratePlan->refundable = $ratePlanData['refundable'];
                $ratePlan->breakfast_included = $ratePlanData['breakfast_included'];
                $ratePlan->cancellation_policy = $ratePlanData['cancellation_policy'];
                $ratePlan->deposit_required = $ratePlanData['deposit_required'];
                $ratePlan->deposit_percentage = $ratePlanData['deposit_percentage'];
                $ratePlan->active = true;
                
                // Set country restrictions (randomly)
                $restrictionType = rand(1, 10) <= 3 ? rand(1, 2) : 0; // 30% chance of having restrictions
                
                switch ($restrictionType) {
                    case 0: // No restrictions
                        $ratePlan->country_restriction_type = 'none';
                        break;
                    case 1: // Include only
                        $ratePlan->country_restriction_type = 'include_only';
                        $ratePlan->applicable_countries = array_slice($popularCountries, 0, rand(3, 6));
                        break;
                    case 2: // Exclude only
                        $ratePlan->country_restriction_type = 'exclude_only';
                        $ratePlan->excluded_countries = array_slice($popularCountries, 0, rand(2, 4));
                        break;
                }
                
                $ratePlan->save();
            }
        }
    }
}
