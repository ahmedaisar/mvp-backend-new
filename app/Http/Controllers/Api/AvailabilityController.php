<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AvailabilityRequest;
use App\Http\Resources\Api\AvailabilityResource;
use App\Models\RatePlan;
use App\Services\InventoryService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @group Availability & Pricing
 * 
 * Query room availability and pricing for specific resorts and date ranges.
 */
class AvailabilityController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private PricingService $pricingService
    ) {}

    /**
     * Check availability for specific dates and room types
     */
    public function checkAvailability(AvailabilityRequest $request)
    {
        try {
            $validated = $request->validated();
            
            // Get rate plans for the resort
            $ratePlans = RatePlan::where('resort_id', $validated['resort_id'])
                ->where('status', 'active')
                ->with('roomType')
                ->get();

            $availability = [];
            $rooms = $validated['rooms'] ?? 1;
            
            foreach ($ratePlans as $ratePlan) {
                $isAvailable = $this->inventoryService->checkAvailability(
                    $ratePlan->id,
                    $validated['check_in'],
                    $validated['check_out'],
                    $rooms
                );
                
                if ($isAvailable) {
                    $pricing = $this->pricingService->calculateTotalPrice(
                        $ratePlan->id,
                        $validated['check_in'],
                        $validated['check_out'],
                        $request->input('promotion_code')
                    );
                    
                    $availability[] = [
                        'rate_plan_id' => $ratePlan->id,
                        'room_type' => $ratePlan->roomType->name,
                        'available' => true,
                        'rooms_requested' => $rooms,
                        'pricing' => $pricing,
                    ];
                }
            }

            return AvailabilityResource::collection(collect($availability));
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check availability',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get calendar view of availability for a resort
     */
    public function getCalendarView(AvailabilityRequest $request)
    {
        try {
            $validated = $request->validated();
            
            // Get rate plans for the resort
            $ratePlans = RatePlan::where('resort_id', $validated['resort_id'])
                ->where('status', 'active')
                ->with('roomType')
                ->get();

            $calendarData = [];
            
            foreach ($ratePlans as $ratePlan) {
                $calendar = $this->inventoryService->getInventoryCalendar(
                    $ratePlan->id,
                    $validated['check_in'],
                    $validated['check_out']
                );
                
                $calendarData[] = [
                    'rate_plan_id' => $ratePlan->id,
                    'room_type' => $ratePlan->roomType->name,
                    'calendar' => $calendar,
                ];
            }

            return response()->json([
                'resort_id' => $validated['resort_id'],
                'date_range' => [
                    'start' => $validated['check_in'],
                    'end' => $validated['check_out']
                ],
                'availability_calendar' => $calendarData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get calendar view',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Rate Calendar
     * 
     * Returns daily rates for a specific rate plan over a date range.
     * Includes seasonal rates, restrictions, and special offers.
     */
    public function rates(int $ratePlanId, AvailabilityRequest $request): JsonResponse
    {
        try {
            $params = $request->validated();
            
            // Get the rate plan
            $ratePlan = RatePlan::with('roomType')->findOrFail($ratePlanId);
            
            // Get nightly breakdown
            $breakdown = $this->pricingService->getNightlyBreakdown(
                $ratePlanId,
                $params['check_in'],
                $params['check_out']
            );
            
            return response()->json([
                'success' => true,
                'data' => [
                    'rate_plan_id' => $ratePlanId,
                    'room_type' => $ratePlan->roomType->name,
                    'rates' => $breakdown,
                    'currency' => 'MVR',
                    'period' => [
                        'check_in' => $params['check_in'],
                        'check_out' => $params['check_out'],
                    ],
                ],
                'message' => 'Rate calendar retrieved successfully.',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching rate calendar.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
