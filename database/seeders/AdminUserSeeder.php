<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        // $admin = User::create([
        //     'name' => 'Admin User',
        //     'email' => 'admin@atolldiscovery.com',
        //     'email_verified_at' => now(),
        //     'password' => Hash::make('password'),
        //     'role' => 'admin',
        // ]);

        // // Assign super admin role
        // $admin->assignRole('super_admin');

        // Create resort manager user
        $resortManager = User::create([
            'name' => 'Resort Manager',
            'email' => 'manager@atolldiscovery.com',
            'email_verified_at' => now(),
            'password' => Hash::make('manager'),
            'role' => 'resort_manager',
        ]);

        $resortManager->assignRole('resort_manager');

        // Create booking agent user (using agency_operator role from enum)
        $bookingAgent = User::create([
            'name' => 'Booking Agent',
            'email' => 'agent@atolldiscovery.com',
            'email_verified_at' => now(),
            'password' => Hash::make('agency'),
            'role' => 'agency_operator',
        ]);

        $bookingAgent->assignRole('booking_agent');

        // Create booking agent user (using agency_operator role from enum)
        $bookingAgent = User::create([
            'name' => 'Traveler',
            'email' => 'traveler@atolldiscovery.com',
            'email_verified_at' => now(),
            'password' => Hash::make('traveler'),
            'role' => 'customer_service',
        ]);

        $bookingAgent->assignRole('customer_service');
    }
}
