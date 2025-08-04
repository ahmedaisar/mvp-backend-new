<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Resort Management
            'view_resorts',
            'create_resorts',
            'edit_resorts',
            'delete_resorts',
            
            // Room Type Management
            'view_room_types',
            'create_room_types',
            'edit_room_types',
            'delete_room_types',
            
            // Rate Plan Management
            'view_rate_plans',
            'create_rate_plans',
            'edit_rate_plans',
            'delete_rate_plans',
            
            // Booking Management
            'view_bookings',
            'create_bookings',
            'edit_bookings',
            'delete_bookings',
            'confirm_bookings',
            'cancel_bookings',
            
            // User Management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            
            // Reports & Analytics
            'view_reports',
            'view_analytics',
            
            // System Settings
            'manage_settings',
            'manage_permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create and assign permissions to roles
        
        // Super Admin - has all permissions
        $superAdmin = Role::create(['name' => 'super_admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Resort Manager - can manage resorts, room types, rate plans, and bookings
        $resortManager = Role::create(['name' => 'resort_manager']);
        $resortManager->givePermissionTo([
            'view_resorts',
            'edit_resorts',
            'view_room_types',
            'create_room_types',
            'edit_room_types',
            'delete_room_types',
            'view_rate_plans',
            'create_rate_plans',
            'edit_rate_plans',
            'delete_rate_plans',
            'view_bookings',
            'create_bookings',
            'edit_bookings',
            'confirm_bookings',
            'cancel_bookings',
            'view_reports',
            'view_analytics',
        ]);

        // Booking Agent - can manage bookings and view resorts
        $bookingAgent = Role::create(['name' => 'booking_agent']);
        $bookingAgent->givePermissionTo([
            'view_resorts',
            'view_room_types',
            'view_rate_plans',
            'view_bookings',
            'create_bookings',
            'edit_bookings',
            'confirm_bookings',
            'cancel_bookings',
        ]);

        // Customer Service - can view and edit bookings, limited access
        $customerService = Role::create(['name' => 'customer_service']);
        $customerService->givePermissionTo([
            'view_resorts',
            'view_room_types',
            'view_bookings',
            'edit_bookings',
            'cancel_bookings',
        ]);

        // Guest - minimal permissions for customers
        $guest = Role::create(['name' => 'guest']);
        $guest->givePermissionTo([
            'view_resorts',
            'view_room_types',
            'view_rate_plans',
        ]);
    }
}
