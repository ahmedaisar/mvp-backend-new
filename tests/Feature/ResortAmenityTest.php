<?php

use App\Models\Resort;
use App\Models\Amenity;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->seed(RolePermissionSeeder::class);
    
    // Create a super admin user
    $this->user = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'email_verified_at' => now(),
    ]);
    
    // Assign super admin role
    $this->user->assignRole('super_admin');
    
    // Login as admin
    $this->actingAs($this->user);
});

test('can create a resort with amenities directly in database', function () {
    // Create amenities to attach
    $amenities = Amenity::factory(3)->create([
        'name' => fn () => "Test Amenity " . rand(1, 100),
    ]);
    
    // Create resort
    $resort = Resort::factory()->create([
        'name' => 'Direct Test Resort',
        'slug' => 'direct-test-resort',
        'description' => 'This is a test resort with amenities',
        'tax_rules' => ['gst' => 10, 'service_fee' => 12],
    ]);
    
    // Attach amenities
    $resort->amenities()->attach($amenities->pluck('id'));
    
    // Assertions
    $this->assertDatabaseHas('resorts', [
        'name' => 'Direct Test Resort',
        'slug' => 'direct-test-resort',
    ]);
    
    $this->assertCount(3, $resort->amenities);
    
    // Check each amenity is attached
    foreach ($amenities as $amenity) {
        $this->assertDatabaseHas('resort_amenities', [
            'resort_id' => $resort->id,
            'amenity_id' => $amenity->id,
        ]);
    }
});

test('can retrieve amenities for a resort', function () {
    // Create amenities
    $amenities = collect([]);
    for ($i = 1; $i <= 3; $i++) {
        $amenities->push(Amenity::factory()->create([
            'name' => "Test Amenity $i",
        ]));
    }
    
    // Create resort
    $resort = Resort::factory()->create([
        'name' => 'Resort With Amenities',
        'slug' => 'resort-with-amenities',
        'description' => 'A resort with amenities for testing',
    ]);
    
    // Attach amenities to the resort
    $resort->amenities()->attach($amenities->pluck('id'));
    
    // Retrieve and check
    $retrievedResort = Resort::with('amenities')->find($resort->id);
    
    expect($retrievedResort->amenities)->toHaveCount(3);
    
    // Check that each amenity is included
    foreach ($amenities as $amenity) {
        $this->assertTrue($retrievedResort->amenities->contains('id', $amenity->id));
        $this->assertTrue($retrievedResort->amenities->contains('name', $amenity->name));
    }
});

test('can update resort amenities', function () {
    // Create initial amenities
    $initialAmenities = Amenity::factory(2)->create();
    
    // Create resort with initial amenities
    $resort = Resort::factory()->create([
        'name' => 'Resort To Update',
        'slug' => 'resort-to-update',
    ]);
    
    $resort->amenities()->attach($initialAmenities->pluck('id'));
    
    // Create new amenities for update
    $newAmenities = Amenity::factory(3)->create();
    
    // Update resort amenities (sync replaces all existing with new ones)
    $resort->amenities()->sync($newAmenities->pluck('id'));
    
    // Verify amenities were updated
    $resort = $resort->fresh(['amenities']);
    
    expect($resort->amenities)->toHaveCount(3);
    
    // Old amenities should not be associated anymore
    foreach ($initialAmenities as $amenity) {
        $this->assertDatabaseMissing('resort_amenities', [
            'resort_id' => $resort->id,
            'amenity_id' => $amenity->id,
        ]);
    }
    
    // New amenities should be associated
    foreach ($newAmenities as $amenity) {
        $this->assertDatabaseHas('resort_amenities', [
            'resort_id' => $resort->id,
            'amenity_id' => $amenity->id,
        ]);
    }
});
