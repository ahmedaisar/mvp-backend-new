<?php

namespace Tests\Feature\Filament;

use App\Models\Resort;
use App\Models\User;
use App\Models\Amenity;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminResortResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(RolePermissionSeeder::class);

        // Create an admin user with a known password
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Assign super_admin role
        $this->admin->assignRole('super_admin');
        
        // Login as admin
        $this->actingAs($this->admin);
    }

    /** @test */
    public function admin_can_create_resort_through_model()
    {
        // Admin creates a resort directly through the model
        $resort = Resort::create([
            'name' => 'Admin Created Resort',
            'slug' => 'admin-created-resort',
            'location' => 'Admin Test Location',
            'island' => 'Admin Test Island',
            'atoll' => 'Admin Test Atoll',
            'coordinates' => '4.1755,73.4852',
            'contact_email' => 'admin@test-resort.com',
            'contact_phone' => '+960-123-4567',
            'resort_type' => 'resort',
            'star_rating' => 5,
            'currency' => 'MVR',
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'description' => 'This is a resort created by admin',
            'tax_rules' => ['gst' => 10, 'service_fee' => 12],
            'active' => true,
        ]);
        
        $this->assertDatabaseHas('resorts', [
            'name' => 'Admin Created Resort',
            'slug' => 'admin-created-resort',
        ]);
        
        // Admin should be able to read the resort
        $retrievedResort = Resort::find($resort->id);
        $this->assertEquals('Admin Created Resort', $retrievedResort->name);
        $this->assertEquals('admin-created-resort', $retrievedResort->slug);
    }
    
    /** @test */
    public function admin_can_create_resort_with_amenities_through_model()
    {
        // Create amenities
        $amenities = Amenity::factory(3)->create();
        
        // Admin creates a resort
        $resort = Resort::create([
            'name' => 'Admin Resort With Amenities',
            'slug' => 'admin-resort-with-amenities',
            'location' => 'Admin Test Location',
            'island' => 'Admin Test Island',
            'atoll' => 'Admin Test Atoll',
            'coordinates' => '4.1755,73.4852',
            'contact_email' => 'admin@test-resort.com',
            'contact_phone' => '+960-123-4567',
            'resort_type' => 'resort',
            'star_rating' => 5,
            'currency' => 'MVR',
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'description' => 'This is a resort with amenities created by admin',
            'tax_rules' => ['gst' => 10, 'service_fee' => 12],
            'active' => true,
        ]);
        
        // Admin attaches amenities
        $resort->amenities()->attach($amenities->pluck('id'));
        
        $this->assertDatabaseHas('resorts', [
            'name' => 'Admin Resort With Amenities',
            'slug' => 'admin-resort-with-amenities',
        ]);
        
        // Verify amenities are attached
        $this->assertCount(3, $resort->amenities);
        
        // Check each amenity is attached
        foreach ($amenities as $amenity) {
            $this->assertDatabaseHas('resort_amenities', [
                'resort_id' => $resort->id,
                'amenity_id' => $amenity->id,
            ]);
        }
    }

    /** @test */
    public function admin_can_view_resort_details_through_model()
    {
        // Create a resort to view
        $resort = Resort::factory()->create([
            'name' => 'Admin Viewable Resort',
            'slug' => 'admin-viewable-resort',
            'description' => 'This is a viewable resort for admin',
        ]);
        
        // Admin retrieves resort details
        $retrievedResort = Resort::find($resort->id);
        
        $this->assertEquals('Admin Viewable Resort', $retrievedResort->name);
        $this->assertEquals('admin-viewable-resort', $retrievedResort->slug);
        $this->assertEquals('This is a viewable resort for admin', $retrievedResort->description);
    }
    
    /** @test */
    public function admin_can_view_resort_with_amenities_through_model()
    {
        // Create amenities
        $amenities = [];
        for ($i = 1; $i <= 3; $i++) {
            $amenities[] = Amenity::factory()->create([
                'name' => "Admin View Amenity $i",
                'code' => "admin-view-amenity-$i",
            ]);
        }
        
        // Create a resort with amenities
        $resort = Resort::factory()->create([
            'name' => 'Admin Resort With Viewable Amenities',
            'slug' => 'admin-resort-with-viewable-amenities',
            'description' => 'This is a resort with viewable amenities for admin',
        ]);
        
        // Attach amenities
        $resort->amenities()->attach(collect($amenities)->pluck('id'));
        
        // Admin retrieves resort with amenities
        $retrievedResort = Resort::with('amenities')->find($resort->id);
        
        $this->assertEquals('Admin Resort With Viewable Amenities', $retrievedResort->name);
        $this->assertCount(3, $retrievedResort->amenities);
        
        // Verify each amenity is included
        foreach ($amenities as $index => $amenity) {
            $this->assertTrue($retrievedResort->amenities->contains('name', "Admin View Amenity " . ($index + 1)));
        }
    }

    /** @test */
    public function admin_can_update_resort_through_model()
    {
        // Create a resort to update
        $resort = Resort::factory()->create([
            'name' => 'Admin Resort Before Update',
            'slug' => 'admin-resort-before-update',
            'description' => 'Description before admin update',
        ]);
        
        // Admin updates the resort
        $resort->update([
            'name' => 'Admin Resort After Update',
            'slug' => 'admin-resort-after-update',
            'location' => 'Admin Updated Location',
            'description' => 'Description after admin update',
        ]);
        
        $this->assertDatabaseHas('resorts', [
            'id' => $resort->id,
            'name' => 'Admin Resort After Update',
            'slug' => 'admin-resort-after-update',
            'location' => 'Admin Updated Location',
            'description' => 'Description after admin update',
        ]);
        
        // Verify the update
        $updatedResort = Resort::find($resort->id);
        $this->assertEquals('Admin Resort After Update', $updatedResort->name);
        $this->assertEquals('admin-resort-after-update', $updatedResort->slug);
    }
    
    /** @test */
    public function admin_can_update_resort_amenities_through_model()
    {
        // Create initial amenities
        $initialAmenities = Amenity::factory(2)->create();
        
        // Create a resort with initial amenities
        $resort = Resort::factory()->create([
            'name' => 'Admin Resort Before Amenity Update',
            'slug' => 'admin-resort-before-amenity-update',
        ]);
        
        // Attach initial amenities
        $resort->amenities()->attach($initialAmenities->pluck('id'));
        
        // Create new amenities for update
        $newAmenities = Amenity::factory(3)->create();
        
        // Admin updates resort amenities
        $resort->update([
            'name' => 'Admin Resort After Amenity Update',
            'slug' => 'admin-resort-after-amenity-update',
        ]);
        
        // Sync amenities (replace old with new)
        $resort->amenities()->sync($newAmenities->pluck('id'));
        
        $this->assertDatabaseHas('resorts', [
            'id' => $resort->id,
            'name' => 'Admin Resort After Amenity Update',
            'slug' => 'admin-resort-after-amenity-update',
        ]);
        
        // Verify amenities were updated
        $updatedResort = Resort::with('amenities')->find($resort->id);
        $this->assertCount(3, $updatedResort->amenities);
        
        // Verify new amenities are attached
        foreach ($newAmenities as $amenity) {
            $this->assertDatabaseHas('resort_amenities', [
                'resort_id' => $resort->id,
                'amenity_id' => $amenity->id,
            ]);
        }
        
        // Verify old amenities are detached
        foreach ($initialAmenities as $amenity) {
            $this->assertDatabaseMissing('resort_amenities', [
                'resort_id' => $resort->id,
                'amenity_id' => $amenity->id,
            ]);
        }
    }
    
    /** @test */
    public function admin_can_delete_resort_through_model()
    {
        // Create a resort to delete
        $resort = Resort::factory()->create([
            'name' => 'Admin Resort To Delete',
            'slug' => 'admin-resort-to-delete',
        ]);
        
        // Admin deletes the resort (soft delete)
        $resort->delete();
        
        // Verify resort is soft deleted
        $this->assertSoftDeleted('resorts', [
            'id' => $resort->id,
        ]);
    }
}
