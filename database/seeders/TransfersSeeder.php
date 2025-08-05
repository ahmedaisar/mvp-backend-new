<?php

namespace Database\Seeders;

use App\Models\Resort;
use App\Models\Transfer;
use Illuminate\Database\Seeder;

class TransfersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all active resorts
        $resorts = Resort::all();
        
        foreach ($resorts as $resort) {
            // Create shared transfer options
            $this->createSharedTransfers($resort);
            
            // Create private transfer options
            $this->createPrivateTransfers($resort);
        }
    }
    
    /**
     * Create shared transfer options for a resort
     *
     * @param Resort $resort
     */
    private function createSharedTransfers(Resort $resort): void
    {
        // Create standard shared speedboat transfer
        Transfer::create([
            'resort_id' => $resort->id,
            'name' => 'Shared Speedboat Transfer',
            'type' => 'shared',
            'route' => 'Male International Airport - ' . $resort->name,
            'price' => rand(35, 75),
            'capacity' => rand(12, 20),
            'description' => 'Regular shared speedboat transfer from Male International Airport to ' . $resort->name . '. Transfer operates on scheduled times, please check with the resort for the schedule.',
            'active' => true,
        ]);
        
        // If resort is in North or South Male Atoll, create public ferry option
        if (str_contains($resort->atoll, 'Male Atoll')) {
            Transfer::create([
                'resort_id' => $resort->id,
                'name' => 'Public Ferry Transfer',
                'type' => 'shared',
                'route' => 'Male City - ' . $resort->name,
                'price' => rand(5, 15),
                'capacity' => rand(30, 50),
                'description' => 'Economical public ferry service from Male City to ' . $resort->name . '. Operates on limited schedule, typically once or twice daily.',
                'active' => true,
            ]);
        }
        
        // For far atolls, create domestic flight + speedboat
        if (!str_contains($resort->atoll, 'Male Atoll')) {
            Transfer::create([
                'resort_id' => $resort->id,
                'name' => 'Domestic Flight + Speedboat',
                'type' => 'shared',
                'route' => 'Male Airport - Domestic Airport - ' . $resort->name,
                'price' => rand(250, 350),
                'capacity' => rand(10, 16),
                'description' => 'Combined transfer with domestic flight to local airport followed by speedboat transfer to ' . $resort->name . '.',
                'active' => true,
            ]);
        }
    }
    
    /**
     * Create private transfer options for a resort
     *
     * @param Resort $resort
     */
    private function createPrivateTransfers(Resort $resort): void
    {
        // Create private speedboat transfer
        Transfer::create([
            'resort_id' => $resort->id,
            'name' => 'Private Speedboat Transfer',
            'type' => 'private',
            'route' => 'Male International Airport - ' . $resort->name,
            'price' => rand(150, 300),
            'capacity' => rand(6, 10),
            'description' => 'Exclusive private speedboat transfer from Male International Airport to ' . $resort->name . '. Available 24/7, subject to weather conditions.',
            'active' => true,
        ]);
        
        // Create seaplane transfer for resorts in distant atolls
        if (!str_contains($resort->atoll, 'Male Atoll')) {
            Transfer::create([
                'resort_id' => $resort->id,
                'name' => 'Seaplane Transfer',
                'type' => 'private',
                'route' => 'Male International Airport - ' . $resort->name,
                'price' => rand(400, 600),
                'capacity' => rand(6, 15),
                'description' => 'Scenic seaplane transfer from Male International Airport directly to ' . $resort->name . '. Available during daylight hours only (typically 6 AM to 4 PM).',
                'active' => true,
            ]);
        }
        
        // Luxury yacht transfer for premium resorts
        if ($resort->star_rating >= 5) {
            Transfer::create([
                'resort_id' => $resort->id,
                'name' => 'Luxury Yacht Transfer',
                'type' => 'private',
                'route' => 'Male International Airport - ' . $resort->name,
                'price' => rand(800, 1200),
                'capacity' => rand(4, 8),
                'description' => 'Premium yacht transfer offering exceptional comfort and luxury from Male International Airport to ' . $resort->name . '. Complimentary refreshments and Wi-Fi included.',
                'active' => true,
            ]);
        }
    }
}
