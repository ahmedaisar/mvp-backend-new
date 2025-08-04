<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\InventoryService;
use App\Models\Resort;
use App\Models\RoomType;
use App\Models\RatePlan;
use App\Models\SeasonalRate;
use App\Models\GuestProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed basic data for testing
        $this->artisan('db:seed', ['--class' => 'SiteSettingsSeeder']);
        $this->artisan('db:seed', ['--class' => 'AmenitiesSeeder']);
    }

    /** @test */
    public function it_can_search_availability_and_calculate_pricing()
    {
        // Create test data
        $resort = Resort::factory()->create([
            'name' => 'Test Resort',
            'active' => true,
        ]);

        $roomType = RoomType::factory()->create([
            'resort_id' => $resort->id,
            'name' => 'Ocean Villa',
            'max_adults' => 2,
            'max_children' => 2,
            'active' => true,
        ]);

        $ratePlan = RatePlan::factory()->create([
            'room_type_id' => $roomType->id,
            'name' => 'All Inclusive',
            'active' => true,
        ]);

        SeasonalRate::factory()->create([
            'rate_plan_id' => $ratePlan->id,
            'nightly_price' => 500.00,
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'active' => true,
        ]);

        // Test services
        $bookingService = app(BookingService::class);
        $pricingService = app(PricingService::class);

        // Search availability
        $available = $bookingService->searchAvailability([
            'check_in' => now()->addDays(7)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
            'adults' => 2,
            'children' => 0,
        ]);

        $this->assertNotEmpty($available);
        $this->assertEquals($resort->id, $available->first()['resort']->id);

        // Calculate pricing
        $pricing = $pricingService->calculateTotalPrice(
            $ratePlan->id,
            now()->addDays(7)->toDateString(),
            now()->addDays(10)->toDateString()
        );

        $this->assertArrayHasKey('base_price', $pricing);
        $this->assertArrayHasKey('total', $pricing);
        $this->assertEquals(1500.00, $pricing['base_price']); // 3 nights × $500
    }

    /** @test */
    public function it_can_create_booking_with_inventory_management()
    {
        // Create test data
        $resort = Resort::factory()->create(['active' => true]);
        $roomType = RoomType::factory()->create([
            'resort_id' => $resort->id,
            'active' => true,
        ]);
        $ratePlan = RatePlan::factory()->create([
            'room_type_id' => $roomType->id,
            'active' => true,
        ]);

        SeasonalRate::factory()->create([
            'rate_plan_id' => $ratePlan->id,
            'nightly_price' => 300.00,
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'active' => true,
        ]);

        $guestProfile = GuestProfile::factory()->create();

        // Test services
        $bookingService = app(BookingService::class);
        $inventoryService = app(InventoryService::class);

        // Check initial availability
        $this->assertTrue($inventoryService->checkAvailability(
            $ratePlan->id,
            now()->addDays(5)->toDateString(),
            now()->addDays(7)->toDateString(),
            1
        ));

        // Create booking
        $booking = $bookingService->createBooking([
            'resort_id' => $resort->id,
            'room_type_id' => $roomType->id,
            'rate_plan_id' => $ratePlan->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'guest' => [
                'full_name' => $guestProfile->full_name,
                'email' => $guestProfile->email,
                'phone' => $guestProfile->phone,
            ],
        ]);

        $this->assertNotNull($booking);
        $this->assertEquals('pending', $booking->status);
        $this->assertEquals(600.00, $booking->total_price_usd); // 2 nights × $300

        // Verify inventory was reserved
        $reserved = $inventoryService->reserveInventory($booking->id);
        $this->assertNotEmpty($reserved);
    }

    /** @test */
    public function services_are_properly_registered_in_container()
    {
        $this->assertInstanceOf(BookingService::class, app(BookingService::class));
        $this->assertInstanceOf(PricingService::class, app(PricingService::class));
        $this->assertInstanceOf(InventoryService::class, app(InventoryService::class));
        
        // Test singleton behavior
        $service1 = app(BookingService::class);
        $service2 = app(BookingService::class);
        $this->assertSame($service1, $service2);
    }
}
