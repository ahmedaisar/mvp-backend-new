<?php

use App\Filament\Resources\ResortResource;
use App\Models\Resort;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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

describe('Resort Resource CRUD Operations', function () {
    
    it('can render resort list page', function () {
        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->assertSuccessful();
    });

    it('can list resorts in table', function () {
        $resorts = Resort::factory(3)->create();

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->assertCanSeeTableRecords($resorts);
    });

    it('can render resort create page', function () {
        Livewire::test(ResortResource\Pages\CreateResort::class)
            ->assertSuccessful();
    });

    it('can create a resort', function () {
        $newData = [
            'name' => 'Paradise Resort',
            'slug' => 'paradise-resort',
            'location' => 'Maafushi Island, South Male Atoll, Maldives',
            'island' => 'Maafushi',
            'atoll' => 'South Male Atoll',
            'coordinates' => '4.1755,73.4852',
            'contact_email' => 'info@paradiseresort.com',
            'contact_phone' => '+960-123-4567',
            'resort_type' => 'resort',
            'star_rating' => 5,
            'currency' => 'MVR',
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'active' => true,
        ];

        Livewire::test(ResortResource\Pages\CreateResort::class)
            ->fillForm($newData)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('resorts', [
            'name' => 'Paradise Resort',
            'slug' => 'paradise-resort',
            'island' => 'Maafushi',
            'atoll' => 'South Male Atoll',
            'contact_email' => 'info@paradiseresort.com',
        ]);
    });

    it('validates required fields when creating resort', function () {
        Livewire::test(ResortResource\Pages\CreateResort::class)
            ->fillForm([
                'name' => '',
                'slug' => '',
                'location' => '',
                'island' => '',
                'atoll' => '',
                'contact_email' => '',
                'contact_phone' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'required',
                'location' => 'required',
                'island' => 'required',
                'atoll' => 'required',
                'contact_email' => 'required',
                'contact_phone' => 'required',
            ]);
    });

    it('validates email format when creating resort', function () {
        Livewire::test(ResortResource\Pages\CreateResort::class)
            ->fillForm([
                'name' => 'Test Resort',
                'slug' => 'test-resort',
                'location' => 'Test Location',
                'island' => 'Test Island',
                'atoll' => 'Test Atoll',
                'contact_email' => 'invalid-email',
                'contact_phone' => '+960-123-4567',
            ])
            ->call('create')
            ->assertHasFormErrors(['contact_email' => 'email']);
    });

    it('ensures resort slug is unique', function () {
        Resort::factory()->create(['slug' => 'existing-resort']);

        Livewire::test(ResortResource\Pages\CreateResort::class)
            ->fillForm([
                'name' => 'New Resort',
                'slug' => 'existing-resort',
                'location' => 'Test Location',
                'island' => 'Test Island',
                'atoll' => 'Test Atoll',
                'contact_email' => 'test@resort.com',
                'contact_phone' => '+960-123-4567',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    });

    it('can render resort view page', function () {
        $resort = Resort::factory()->create();

        Livewire::test(ResortResource\Pages\ViewResort::class, ['record' => $resort->getRouteKey()])
            ->assertSuccessful();
    });

    it('can retrieve resort data for view', function () {
        $resort = Resort::factory()->create([
            'name' => 'Test Resort View',
            'slug' => 'test-resort-view',
        ]);

        Livewire::test(ResortResource\Pages\ViewResort::class, [
            'record' => $resort->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSeeHtml('Test Resort View');
    });

    it('can render resort edit page', function () {
        $resort = Resort::factory()->create();

        Livewire::test(ResortResource\Pages\EditResort::class, ['record' => $resort->getRouteKey()])
            ->assertSuccessful();
    });

    it('can retrieve resort data for editing', function () {
        $resort = Resort::factory()->create([
            'name' => 'Edit Test Resort',
            'slug' => 'edit-test-resort',
        ]);

        Livewire::test(ResortResource\Pages\EditResort::class, [
            'record' => $resort->getRouteKey(),
        ])
            ->assertFormSet([
                'name' => 'Edit Test Resort',
                'slug' => 'edit-test-resort',
            ]);
    });

    it('can update resort', function () {
        $resort = Resort::factory()->create([
            'name' => 'Original Resort',
            'slug' => 'original-resort',
        ]);

        $newData = [
            'name' => 'Updated Resort Name',
            'slug' => 'updated-resort-slug',
            'location' => 'Updated Location',
            'island' => 'Updated Island',
            'atoll' => 'Updated Atoll',
            'contact_email' => 'updated@resort.com',
            'star_rating' => 4,
        ];

        Livewire::test(ResortResource\Pages\EditResort::class, [
            'record' => $resort->getRouteKey(),
        ])
            ->fillForm($newData)
            ->call('save')
            ->assertHasNoFormErrors();

        expect($resort->fresh())
            ->name->toBe('Updated Resort Name')
            ->slug->toBe('updated-resort-slug')
            ->location->toBe('Updated Location')
            ->island->toBe('Updated Island')
            ->atoll->toBe('Updated Atoll')
            ->contact_email->toBe('updated@resort.com')
            ->star_rating->toBe(4);
    });

    it('validates required fields when updating resort', function () {
        $resort = Resort::factory()->create();

        Livewire::test(ResortResource\Pages\EditResort::class, [
            'record' => $resort->getRouteKey(),
        ])
            ->fillForm([
                'name' => '',
                'slug' => '',
                'location' => '',
                'island' => '',
                'atoll' => '',
                'contact_email' => '',
                'contact_phone' => '',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'required',
                'location' => 'required',
                'island' => 'required', 
                'atoll' => 'required',
                'contact_email' => 'required',
                'contact_phone' => 'required',
            ]);
    });

    it('can delete resort', function () {
        $resort = Resort::factory()->create();

        Livewire::test(ResortResource\Pages\EditResort::class, [
            'record' => $resort->getRouteKey(),
        ])
            ->callAction(DeleteAction::class);

        expect($resort->fresh()->trashed())->toBeTrue();
    });

    it('can bulk delete resorts', function () {
        $resorts = Resort::factory(3)->create();

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->callTableBulkAction('delete', $resorts);

        foreach ($resorts as $resort) {
            expect($resort->fresh()->trashed())->toBeTrue();
        }
    });

});

describe('Resort Resource Table Features', function () {

    it('can search resorts by name', function () {
        $searchableResort = Resort::factory()->create(['name' => 'Searchable Paradise Resort']);
        $otherResort = Resort::factory()->create(['name' => 'Different Resort']);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->searchTable('Paradise')
            ->assertCanSeeTableRecords([$searchableResort])
            ->assertCanNotSeeTableRecords([$otherResort]);
    });

    it('can search resorts by island', function () {
        $searchableResort = Resort::factory()->create(['island' => 'Paradise Island']);
        $otherResort = Resort::factory()->create(['island' => 'Other Island']);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->searchTable('Paradise')
            ->assertCanSeeTableRecords([$searchableResort])
            ->assertCanNotSeeTableRecords([$otherResort]);
    });

    it('can filter resorts by star rating', function () {
        $fiveStarResort = Resort::factory()->create(['star_rating' => 5]);
        $fourStarResort = Resort::factory()->create(['star_rating' => 4]);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->filterTable('star_rating', '5')
            ->assertCanSeeTableRecords([$fiveStarResort])
            ->assertCanNotSeeTableRecords([$fourStarResort]);
    });

    it('can filter resorts by resort type', function () {
        $resortType = Resort::factory()->create(['resort_type' => 'resort']);
        $hotelType = Resort::factory()->create(['resort_type' => 'hotel']);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->filterTable('resort_type', 'resort')
            ->assertCanSeeTableRecords([$resortType])
            ->assertCanNotSeeTableRecords([$hotelType]);
    });

    it('can filter active resorts', function () {
        $activeResort = Resort::factory()->create(['active' => true]);
        $inactiveResort = Resort::factory()->create(['active' => false]);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->filterTable('active', true)
            ->assertCanSeeTableRecords([$activeResort])
            ->assertCanNotSeeTableRecords([$inactiveResort]);
    });

    it('can sort resorts by name', function () {
        $resortA = Resort::factory()->create(['name' => 'A Resort']);
        $resortZ = Resort::factory()->create(['name' => 'Z Resort']);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->sortTable('name')
            ->assertCanSeeTableRecords([$resortA, $resortZ], inOrder: true);
    });

    it('can sort resorts by star rating', function () {
        $lowRatingResort = Resort::factory()->create(['star_rating' => 3]);
        $highRatingResort = Resort::factory()->create(['star_rating' => 5]);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->sortTable('star_rating', 'desc')
            ->assertCanSeeTableRecords([$highRatingResort, $lowRatingResort], inOrder: true);
    });

});

describe('Resort Resource Bulk Actions', function () {

    it('can bulk activate resorts', function () {
        $inactiveResorts = Resort::factory(3)->create(['active' => false]);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->callTableBulkAction('activate', $inactiveResorts);

        foreach ($inactiveResorts as $resort) {
            expect($resort->fresh()->active)->toBeTrue();
        }
    });

    it('can bulk deactivate resorts', function () {
        $activeResorts = Resort::factory(3)->create(['active' => true]);

        Livewire::test(ResortResource\Pages\ListResorts::class)
            ->callTableBulkAction('deactivate', $activeResorts);

        foreach ($activeResorts as $resort) {
            expect($resort->fresh()->active)->toBeFalse();
        }
    });

});

describe('Resort Resource Authorization', function () {

    it('requires authentication to access resort resource', function () {
        auth()->logout();

        $this->get(ResortResource::getUrl('index'))
            ->assertRedirect('/admin/login');
    });

    it('requires proper permissions to access resort resource', function () {
        // Create a user without admin permissions
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser);

        $this->get(ResortResource::getUrl('index'))
            ->assertStatus(403);
    });

});
