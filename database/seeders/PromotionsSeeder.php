<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Promotion;
use App\Models\Resort;
use Carbon\Carbon;

class PromotionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all resorts to associate promotions with
        $resorts = Resort::all();
        
        if ($resorts->isEmpty()) {
            $this->command->info('No resorts found. Please run the ResortSeeder first.');
            return;
        }
        
        $promotions = [
            [
                'name' => 'Early Bird Discount',
                'code' => 'EARLYBIRD',
                'description' => 'Book at least 30 days in advance and save 15% on your stay.',
                'type' => 'percentage',
                'discount_value' => 15.00,
                'valid_from' => Carbon::now(),
                'valid_until' => Carbon::now()->addYear(),
                'min_nights' => 2,
                'max_uses' => 100,
                'current_uses' => 0,
                'max_uses_per_customer' => 1,
                'min_booking_amount' => 300.00,
                'is_public' => true,
                'active' => true,
                'is_active' => true,
                'auto_apply' => 'best_discount',
                'terms_conditions' => 'Must be booked at least 30 days in advance. Non-refundable.',
            ],
            [
                'name' => 'Summer Special',
                'code' => 'SUMMER25',
                'description' => 'Get 25% off on all summer bookings between June and August.',
                'type' => 'percentage',
                'discount_value' => 25.00,
                'valid_from' => Carbon::create(null, 6, 1),
                'valid_until' => Carbon::create(null, 8, 31),
                'min_nights' => 3,
                'max_uses' => 50,
                'current_uses' => 0,
                'max_uses_per_customer' => 1,
                'min_booking_amount' => 500.00,
                'is_public' => true,
                'active' => true,
                'is_active' => true,
                'auto_apply' => 'best_discount',
                'terms_conditions' => 'Valid for stays between June 1 and August 31. Subject to availability.',
            ],
            [
                'name' => '$100 Resort Credit',
                'code' => 'CREDIT100',
                'description' => 'Book a 5-night stay and receive a $100 resort credit to use during your stay.',
                'type' => 'fixed',
                'discount_value' => 100.00,
                'valid_from' => Carbon::now(),
                'valid_until' => Carbon::now()->addMonths(6),
                'min_nights' => 5,
                'max_uses' => 75,
                'current_uses' => 0,
                'max_uses_per_customer' => 1,
                'min_booking_amount' => 1000.00,
                'is_public' => true,
                'active' => true,
                'is_active' => true,
                'auto_apply' => 'none',
                'terms_conditions' => 'Resort credit can be used for dining, spa, or activities. Cannot be redeemed for cash.',
            ],
            [
                'name' => 'Honeymoon Package',
                'code' => 'HONEYMOON',
                'description' => 'Special package for honeymoon couples with 20% discount and complimentary amenities.',
                'type' => 'percentage',
                'discount_value' => 20.00,
                'valid_from' => Carbon::now(),
                'valid_until' => Carbon::now()->addYear(),
                'min_nights' => 4,
                'max_uses' => 30,
                'current_uses' => 0,
                'max_uses_per_customer' => 1,
                'min_booking_amount' => 800.00,
                'is_public' => true,
                'active' => true,
                'is_active' => true,
                'auto_apply' => 'none',
                'terms_conditions' => 'Must provide proof of recent marriage (within last 6 months) upon check-in.',
            ],
        ];
        
        foreach ($promotions as $promotion) {
            // Assign to a random resort
            $promotion['resort_id'] = $resorts->random()->id;
            
            // Create promotion
            $promo = Promotion::create($promotion);
            
            // Attach to multiple resorts (between 1 and 3 random resorts)
            $randomResorts = $resorts->random(rand(1, min(3, $resorts->count())));
            $promo->resorts()->attach($randomResorts->pluck('id')->toArray());
            
            $this->command->info("Created promotion: {$promo->name}");
        }
    }
}
