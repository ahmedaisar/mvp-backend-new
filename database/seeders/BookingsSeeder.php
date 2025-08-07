<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\GuestProfile;
use App\Models\RatePlan;
use App\Models\Resort;
use App\Models\RoomType;
use App\Models\Promotion;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Faker\Factory as Faker;

class BookingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        // Get related data
        $guestProfiles = GuestProfile::all();
        $resorts = Resort::all();
        $users = User::all(); // Get all users instead of filtering by 'user' role
        
        if ($users->isEmpty()) {
            // Create some regular users if none exist
            for ($i = 0; $i < 5; $i++) {
                $users->push(User::factory()->create([
                    'role' => 'agency_operator', // Use a valid enum value
                ]));
            }
        }
        
        // Create different types of bookings
        $this->createUpcomingBookings($faker, $guestProfiles, $resorts, $users);
        $this->createCompletedBookings($faker, $guestProfiles, $resorts, $users);
        $this->createCancelledBookings($faker, $guestProfiles, $resorts, $users);
        $this->createInHouseBookings($faker, $guestProfiles, $resorts, $users);
    }
    
    /**
     * Create upcoming bookings (confirmed and pending)
     */
    private function createUpcomingBookings($faker, $guestProfiles, $resorts, $users)
    {
        // Create 10-15 upcoming bookings
        $count = rand(10, 15);
        
        for ($i = 0; $i < $count; $i++) {
            $resort = $resorts->random();
            $roomType = $resort->roomTypes()->inRandomOrder()->first();
            
            if (!$roomType) {
                continue; // Skip if no room type is available
            }
            
            $ratePlan = $roomType->ratePlans()->inRandomOrder()->first();
            
            if (!$ratePlan) {
                continue; // Skip if no rate plan is available
            }
            
            $checkInStart = Carbon::now()->addDays(rand(7, 60));
            $checkIn = $checkInStart->copy()->startOfDay();
            $nights = rand(3, 10);
            $checkOut = $checkIn->copy()->addDays($nights);
            
            $user = $users->random();
            $guestProfile = $guestProfiles->random();
            
            // Randomly determine if a promotion should be applied
            $promotion = null;
            if (rand(0, 1)) {
                $promotion = Promotion::where('resort_id', $resort->id)
                    ->where('active', true)
                    ->inRandomOrder()
                    ->first();
            }
            
            // Randomly determine if a transfer should be added
            $transfer = null;
            if (rand(0, 1)) {
                $transfer = Transfer::where('resort_id', $resort->id)
                    ->where('active', true)
                    ->inRandomOrder()
                    ->first();
            }
            
            $booking = new Booking();
            $booking->booking_reference = Booking::generateBookingReference();
            $booking->user_id = $user->id;
            $booking->guest_profile_id = $guestProfile->id;
            $booking->resort_id = $resort->id;
            $booking->room_type_id = $roomType->id;
            $booking->rate_plan_id = $ratePlan->id;
            $booking->check_in = $checkIn;
            $booking->check_out = $checkOut;
            $booking->nights = $nights;
            $booking->adults = rand(1, 2);
            $booking->children = rand(0, 2);
            $booking->status = $i % 3 == 0 ? 'pending' : 'confirmed';
            
            // Initialize required USD fields (will be updated in createBookingItems)
            $booking->subtotal_usd = 0;
            $booking->total_price_usd = 0;
            $booking->currency_rate_usd = 1.0;
            $booking->discount_amount = 0;
            
            if ($promotion) {
                $booking->promotion_id = $promotion->id;
            }
            
            if ($transfer) {
                $booking->transfer_id = $transfer->id;
            }
            
            // Randomly assign commission (if any exist)
            if (rand(0, 3) == 0) { // 25% chance of having commission
                $commission = \App\Models\Commission::inRandomOrder()->first();
                if ($commission) {
                    $booking->commission_id = $commission->id;
                }
            }
            
            $booking->special_requests = rand(0, 1) ? [
                'special_occasion' => $faker->randomElement(['Birthday', 'Anniversary', 'Honeymoon', null]),
                'bed_preference' => $faker->randomElement(['King', 'Twin', null]),
                'room_location' => $faker->randomElement(['High Floor', 'Close to Beach', 'Quiet Area', null]),
            ] : null;
            
            $booking->save();
            
            // Calculate booking cost and create booking items
            $this->createBookingItems($booking, $ratePlan, $transfer);
        }
    }
    
    /**
     * Create completed bookings (past check-out date)
     */
    private function createCompletedBookings($faker, $guestProfiles, $resorts, $users)
    {
        // Create 15-20 completed bookings
        $count = rand(15, 20);
        
        for ($i = 0; $i < $count; $i++) {
            $resort = $resorts->random();
            $roomType = $resort->roomTypes()->inRandomOrder()->first();
            
            if (!$roomType) {
                continue; // Skip if no room type is available
            }
            
            $ratePlan = $roomType->ratePlans()->inRandomOrder()->first();
            
            if (!$ratePlan) {
                continue; // Skip if no rate plan is available
            }
            
            $checkOutEnd = Carbon::now()->subDays(1);
            $checkOut = $checkOutEnd->copy()->startOfDay();
            $nights = rand(3, 10);
            $checkIn = $checkOut->copy()->subDays($nights);
            
            $user = $users->random();
            $guestProfile = $guestProfiles->random();
            
            // Randomly determine if a promotion should be applied
            $promotion = null;
            if (rand(0, 1)) {
                $promotion = Promotion::where('resort_id', $resort->id)
                    ->where('active', true)
                    ->inRandomOrder()
                    ->first();
            }
            
            // Randomly determine if a transfer should be added
            $transfer = null;
            if (rand(0, 1)) {
                $transfer = Transfer::where('resort_id', $resort->id)
                    ->where('active', true)
                    ->inRandomOrder()
                    ->first();
            }
            
            $booking = new Booking();
            $booking->booking_reference = Booking::generateBookingReference();
            $booking->user_id = $user->id;
            $booking->guest_profile_id = $guestProfile->id;
            $booking->resort_id = $resort->id;
            $booking->room_type_id = $roomType->id;
            $booking->rate_plan_id = $ratePlan->id;
            $booking->check_in = $checkIn;
            $booking->check_out = $checkOut;
            $booking->nights = $nights;
            $booking->adults = rand(1, 2);
            $booking->children = rand(0, 2);
            $booking->status = 'completed';
            
            // Initialize required USD fields (will be updated in createBookingItems)
            $booking->subtotal_usd = 0;
            $booking->total_price_usd = 0;
            $booking->currency_rate_usd = 1.0;
            $booking->discount_amount = 0;
            
            if ($promotion) {
                $booking->promotion_id = $promotion->id;
            }
            
            if ($transfer) {
                $booking->transfer_id = $transfer->id;
            }
            
            // Randomly assign commission (if any exist)
            if (rand(0, 3) == 0) { // 25% chance of having commission
                $commission = \App\Models\Commission::inRandomOrder()->first();
                if ($commission) {
                    $booking->commission_id = $commission->id;
                }
            }
            
            $booking->save();
            
            // Calculate booking cost and create booking items
            $this->createBookingItems($booking, $ratePlan, $transfer);
        }
    }
    
    /**
     * Create cancelled bookings
     */
    private function createCancelledBookings($faker, $guestProfiles, $resorts, $users)
    {
        // Create 5-8 cancelled bookings
        $count = rand(5, 8);
        
        for ($i = 0; $i < $count; $i++) {
            $resort = $resorts->random();
            $roomType = $resort->roomTypes()->inRandomOrder()->first();
            
            if (!$roomType) {
                continue; // Skip if no room type is available
            }
            
            $ratePlan = $roomType->ratePlans()->inRandomOrder()->first();
            
            if (!$ratePlan) {
                continue; // Skip if no rate plan is available
            }
            
            // Mix of future and past cancelled bookings
            $isFuture = rand(0, 1);
            
            if ($isFuture) {
                $checkInStart = Carbon::now()->addDays(rand(7, 60));
                $checkIn = $checkInStart->copy()->startOfDay();
            } else {
                $checkInStart = Carbon::now()->subDays(rand(30, 90));
                $checkIn = $checkInStart->copy()->startOfDay();
            }
            
            $nights = rand(3, 10);
            $checkOut = $checkIn->copy()->addDays($nights);
            
            $user = $users->random();
            $guestProfile = $guestProfiles->random();
            
            $booking = new Booking();
            $booking->booking_reference = Booking::generateBookingReference();
            $booking->user_id = $user->id;
            $booking->guest_profile_id = $guestProfile->id;
            $booking->resort_id = $resort->id;
            $booking->room_type_id = $roomType->id;
            $booking->rate_plan_id = $ratePlan->id;
            $booking->check_in = $checkIn;
            $booking->check_out = $checkOut;
            $booking->nights = $nights;
            $booking->adults = rand(1, 2);
            $booking->children = rand(0, 2);
            $booking->status = 'cancelled';
            $booking->cancelled_at = Carbon::now()->subDays(rand(1, 30));
            $booking->cancellation_reason = $faker->randomElement([
                'Change of plans',
                'Emergency situation',
                'Found better deal',
                'Weather concerns',
                'Travel restrictions',
                null
            ]);
            
            // Initialize required USD fields (will be updated in createBookingItems)
            $booking->subtotal_usd = 0;
            $booking->total_price_usd = 0;
            $booking->currency_rate_usd = 1.0;
            $booking->discount_amount = 0;
            
            $booking->save();
            
            // Calculate booking cost and create booking items
            $this->createBookingItems($booking, $ratePlan, null);
        }
    }
    
    /**
     * Create in-house bookings (current check-in < now < check-out)
     */
    private function createInHouseBookings($faker, $guestProfiles, $resorts, $users)
    {
        // Create 3-5 in-house bookings
        $count = rand(3, 5);
        
        for ($i = 0; $i < $count; $i++) {
            $resort = $resorts->random();
            $roomType = $resort->roomTypes()->inRandomOrder()->first();
            
            if (!$roomType) {
                continue; // Skip if no room type is available
            }
            
            $ratePlan = $roomType->ratePlans()->inRandomOrder()->first();
            
            if (!$ratePlan) {
                continue; // Skip if no rate plan is available
            }
            
            $checkIn = Carbon::now()->subDays(rand(1, 3))->startOfDay();
            $nights = rand(5, 10);
            $checkOut = $checkIn->copy()->addDays($nights);
            
            $user = $users->random();
            $guestProfile = $guestProfiles->random();
            
            // Randomly determine if a promotion should be applied
            $promotion = null;
            if (rand(0, 1)) {
                $promotion = Promotion::where('resort_id', $resort->id)
                    ->where('active', true)
                    ->inRandomOrder()
                    ->first();
            }
            
            // Randomly determine if a transfer should be added
            $transfer = null;
            if (rand(0, 1)) {
                $transfer = Transfer::where('resort_id', $resort->id)
                    ->where('active', true)
                    ->inRandomOrder()
                    ->first();
            }
            
            $booking = new Booking();
            $booking->booking_reference = Booking::generateBookingReference();
            $booking->user_id = $user->id;
            $booking->guest_profile_id = $guestProfile->id;
            $booking->resort_id = $resort->id;
            $booking->room_type_id = $roomType->id;
            $booking->rate_plan_id = $ratePlan->id;
            $booking->check_in = $checkIn;
            $booking->check_out = $checkOut;
            $booking->nights = $nights;
            $booking->adults = rand(1, 2);
            $booking->children = rand(0, 2);
            $booking->status = 'confirmed';
            
            // Initialize required USD fields (will be updated in createBookingItems)
            $booking->subtotal_usd = 0;
            $booking->total_price_usd = 0;
            $booking->currency_rate_usd = 1.0;
            $booking->discount_amount = 0;
            
            if ($promotion) {
                $booking->promotion_id = $promotion->id;
            }
            
            if ($transfer) {
                $booking->transfer_id = $transfer->id;
            }
            
            // Randomly assign commission (if any exist)
            if (rand(0, 3) == 0) { // 25% chance of having commission
                $commission = \App\Models\Commission::inRandomOrder()->first();
                if ($commission) {
                    $booking->commission_id = $commission->id;
                }
            }
            
            $booking->special_requests = rand(0, 1) ? [
                'special_occasion' => $faker->randomElement(['Birthday', 'Anniversary', 'Honeymoon', null]),
                'bed_preference' => $faker->randomElement(['King', 'Twin', null]),
                'room_location' => $faker->randomElement(['High Floor', 'Close to Beach', 'Quiet Area', null]),
            ] : null;
            
            $booking->save();
            
            // Calculate booking cost and create booking items
            $this->createBookingItems($booking, $ratePlan, $transfer);
        }
    }
    
    /**
     * Create booking items and set total prices for a booking
     */
    private function createBookingItems(Booking $booking, RatePlan $ratePlan, ?Transfer $transfer): void
    {
        // Generate a realistic nightly rate for the rate plan
        // In a real application, you would use SeasonalRate::calculateTotalForPeriod
        $nightlyRate = rand(150, 800); // Random rate between $150-$800 per night
        $roomSubtotal = $nightlyRate * $booking->nights;
        
        // Create room booking item
        BookingItem::create([
            'booking_id' => $booking->id,
            'item_type' => 'room',
            'item_name' => $ratePlan->name . ' - ' . $booking->roomType->name,
            'unit_price' => $nightlyRate,
            'quantity' => $booking->nights,
            'total_price' => $roomSubtotal,
            'currency' => 'USD',
        ]);
        
        $subtotal = $roomSubtotal;
        
        // Apply promotion discount if applicable
        $discountAmount = 0;
        if ($booking->promotion_id) {
            // Apply a random discount between 10-30%
            $discountRate = rand(10, 30) / 100;
            $discountAmount = $subtotal * $discountRate;
            $subtotal -= $discountAmount;
            
            // Create discount item
            BookingItem::create([
                'booking_id' => $booking->id,
                'item_type' => 'discount',
                'item_name' => 'Promotion Discount',
                'unit_price' => -$discountAmount,
                'quantity' => 1,
                'total_price' => -$discountAmount,
                'currency' => 'USD',
            ]);
        }
        
        // Update booking subtotal and discount amount
        $booking->subtotal_usd = $subtotal;
        $booking->discount_amount = $discountAmount;
        
        // Add taxes (12% GST)
        $taxRate = 0.12;
        $taxAmount = $subtotal * $taxRate;
        
        BookingItem::create([
            'booking_id' => $booking->id,
            'item_type' => 'tax',
            'item_name' => 'GST (12%)',
            'unit_price' => $taxAmount,
            'quantity' => 1,
            'total_price' => $taxAmount,
            'currency' => 'USD',
        ]);
        
        // Add service fee (10%)
        $serviceFeeRate = 0.10;
        $serviceFeeAmount = $subtotal * $serviceFeeRate;
        
        BookingItem::create([
            'booking_id' => $booking->id,
            'item_type' => 'service_fee',
            'item_name' => 'Service Fee (10%)',
            'unit_price' => $serviceFeeAmount,
            'quantity' => 1,
            'total_price' => $serviceFeeAmount,
            'currency' => 'USD',
        ]);
        
        $total = $subtotal + $taxAmount + $serviceFeeAmount;
        
        // Add transfer if selected
        if ($transfer) {
            $transferPrice = $transfer->price;
            $transferQuantity = $booking->adults;
            $transferTotal = $transferPrice * $transferQuantity;
            
            BookingItem::create([
                'booking_id' => $booking->id,
                'item_type' => 'transfer',
                'item_name' => $transfer->name,
                'unit_price' => $transferPrice,
                'quantity' => $transferQuantity,
                'total_price' => $transferTotal,
                'currency' => 'USD',
            ]);
            
            $total += $transferTotal;
        }
        
        // Update final totals
        $booking->total_price_usd = $total;
        $booking->currency_rate_usd = 1.0; // USD is the base currency
        $booking->save();
    }
}
