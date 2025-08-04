<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resort;
use App\Models\RatePlan;
use App\Models\Inventory;
use App\Models\SeasonalRate;
use App\Services\InventoryService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * @group Channel Manager Integration
 * 
 * Synchronize inventory, rates, and bookings with external channel managers and OTAs.
 */
class ChannelManagerController extends Controller
{
    protected $inventoryService;
    protected $pricingService;

    public function __construct(InventoryService $inventoryService, PricingService $pricingService)
    {
        $this->inventoryService = $inventoryService;
        $this->pricingService = $pricingService;
    }

    /**
     * Get inventory data for channel manager
     */
    public function getInventory(Request $request): JsonResponse
    {
        try {
            $resortId = $request->input('resort_id');
            $startDate = $request->input('start_date', now()->toDateString());
            $endDate = $request->input('end_date', now()->addDays(365)->toDateString());
            
            if (!$resortId) {
                return response()->json(['error' => 'Resort ID is required'], 400);
            }
            
            $resort = Resort::findOrFail($resortId);
            $ratePlans = RatePlan::where('resort_id', $resortId)
                ->where('status', 'active')
                ->with('roomType')
                ->get();
            
            $inventoryData = [];
            
            foreach ($ratePlans as $ratePlan) {
                $calendar = $this->inventoryService->getInventoryCalendar(
                    $ratePlan->id,
                    $startDate,
                    $endDate
                );
                
                $inventoryData[] = [
                    'rate_plan_id' => $ratePlan->id,
                    'room_type_code' => $ratePlan->room_type_code,
                    'room_type_name' => $ratePlan->roomType->name,
                    'inventory' => $calendar->map(function ($day) {
                        return [
                            'date' => $day['date'],
                            'available_rooms' => $day['available_rooms'],
                            'total_rooms' => $day['total_rooms'],
                            'min_stay' => 1, // Default minimum stay
                            'max_stay' => 30, // Default maximum stay
                            'closed_to_arrival' => false,
                            'closed_to_departure' => false,
                        ];
                    })
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'resort_id' => $resortId,
                    'resort_code' => $resort->property_code,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ],
                    'inventory' => $inventoryData
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Channel Manager inventory fetch failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to get inventory data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rates data for channel manager
     */
    public function getRates(Request $request): JsonResponse
    {
        try {
            $resortId = $request->input('resort_id');
            $startDate = $request->input('start_date', now()->toDateString());
            $endDate = $request->input('end_date', now()->addDays(365)->toDateString());
            
            if (!$resortId) {
                return response()->json(['error' => 'Resort ID is required'], 400);
            }
            
            $resort = Resort::findOrFail($resortId);
            $ratePlans = RatePlan::where('resort_id', $resortId)
                ->where('status', 'active')
                ->with('roomType')
                ->get();
            
            $ratesData = [];
            
            foreach ($ratePlans as $ratePlan) {
                $rateBreakdown = $this->pricingService->getNightlyBreakdown(
                    $ratePlan->id,
                    $startDate,
                    $endDate
                );
                
                $ratesData[] = [
                    'rate_plan_id' => $ratePlan->id,
                    'room_type_code' => $ratePlan->room_type_code,
                    'room_type_name' => $ratePlan->roomType->name,
                    'rates' => collect($rateBreakdown)->map(function ($rate) {
                        return [
                            'date' => $rate['date'],
                            'base_rate' => $rate['base_price'],
                            'final_rate' => $rate['final_price'],
                            'currency' => 'MVR',
                            'meal_plan' => 'BB', // Bed & Breakfast default
                            'extra_adult_rate' => 0,
                            'extra_child_rate' => 0,
                        ];
                    })
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'resort_id' => $resortId,
                    'resort_code' => $resort->property_code,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ],
                    'rates' => $ratesData
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Channel Manager rates fetch failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to get rates data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update inventory from channel manager
     */
    public function updateInventory(Request $request): JsonResponse
    {
        try {
            $updates = $request->input('updates', []);
            
            if (empty($updates)) {
                return response()->json(['error' => 'No updates provided'], 400);
            }
            
            $results = [];
            
            foreach ($updates as $update) {
                $ratePlanId = $update['rate_plan_id'] ?? null;
                $date = $update['date'] ?? null;
                $availableRooms = $update['available_rooms'] ?? null;
                
                if (!$ratePlanId || !$date || $availableRooms === null) {
                    $results[] = [
                        'status' => 'error',
                        'message' => 'Missing required fields: rate_plan_id, date, available_rooms',
                        'data' => $update
                    ];
                    continue;
                }
                
                try {
                    $inventory = Inventory::updateOrCreate(
                        [
                            'rate_plan_id' => $ratePlanId,
                            'date' => $date
                        ],
                        [
                            'available_rooms' => $availableRooms,
                            'updated_at' => now()
                        ]
                    );
                    
                    $results[] = [
                        'status' => 'success',
                        'message' => 'Inventory updated successfully',
                        'data' => [
                            'rate_plan_id' => $ratePlanId,
                            'date' => $date,
                            'available_rooms' => $availableRooms
                        ]
                    ];
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'status' => 'error',
                        'message' => 'Failed to update inventory: ' . $e->getMessage(),
                        'data' => $update
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Channel Manager inventory update failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update inventory',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update rates from channel manager
     */
    public function updateRates(Request $request): JsonResponse
    {
        try {
            $updates = $request->input('updates', []);
            
            if (empty($updates)) {
                return response()->json(['error' => 'No updates provided'], 400);
            }
            
            $results = [];
            
            foreach ($updates as $update) {
                $ratePlanId = $update['rate_plan_id'] ?? null;
                $date = $update['date'] ?? null;
                $nightlyPrice = $update['nightly_price'] ?? null;
                
                if (!$ratePlanId || !$date || $nightlyPrice === null) {
                    $results[] = [
                        'status' => 'error',
                        'message' => 'Missing required fields: rate_plan_id, date, nightly_price',
                        'data' => $update
                    ];
                    continue;
                }
                
                try {
                    $seasonalRate = SeasonalRate::updateOrCreate(
                        [
                            'rate_plan_id' => $ratePlanId,
                            'start_date' => $date,
                            'end_date' => $date
                        ],
                        [
                            'nightly_price' => $nightlyPrice,
                            'name' => 'Channel Manager Rate',
                            'updated_at' => now()
                        ]
                    );
                    
                    $results[] = [
                        'status' => 'success',
                        'message' => 'Rate updated successfully',
                        'data' => [
                            'rate_plan_id' => $ratePlanId,
                            'date' => $date,
                            'nightly_price' => $nightlyPrice
                        ]
                    ];
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'status' => 'error',
                        'message' => 'Failed to update rate: ' . $e->getMessage(),
                        'data' => $update
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Channel Manager rates update failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update rates',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync resort data with channel manager
     */
    public function syncResort(Request $request): JsonResponse
    {
        try {
            $resortId = $request->input('resort_id');
            
            if (!$resortId) {
                return response()->json(['error' => 'Resort ID is required'], 400);
            }
            
            $resort = Resort::with(['ratePlans.roomType'])->findOrFail($resortId);
            
            // Get latest inventory and rates
            $startDate = now()->toDateString();
            $endDate = now()->addDays(365)->toDateString();
            
            $syncData = [
                'resort' => [
                    'id' => $resort->id,
                    'name' => $resort->name,
                    'property_code' => $resort->property_code,
                    'location' => $resort->location,
                ],
                'room_types' => $resort->ratePlans->map(function ($ratePlan) {
                    return [
                        'rate_plan_id' => $ratePlan->id,
                        'room_type_code' => $ratePlan->room_type_code,
                        'room_type_name' => $ratePlan->roomType->name,
                        'description' => $ratePlan->room_type_description,
                        'max_occupancy' => $ratePlan->roomType->max_occupancy,
                    ];
                }),
                'last_sync' => now()->toISOString()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $syncData,
                'message' => 'Resort data synchronized successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Channel Manager resort sync failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to sync resort data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
