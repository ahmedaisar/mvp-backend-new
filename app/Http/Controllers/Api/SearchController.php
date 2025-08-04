<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Http\Resources\Api\ResortResource;
use App\Services\ResortService;
use Illuminate\Http\JsonResponse;

/**
 * @group Resort Search
 * 
 * Search for resorts based on various criteria including dates, guests, amenities, and pricing.
 */
class SearchController extends Controller
{
    public function __construct(
        private ResortService $resortService
    ) {}

    /**
     * Search Resorts
     * 
     * Filter resorts by date range, guests, amenities, price range, and other criteria.
     * Returns resorts with room types, availability, and rates for the specified period.
     * 
     * @apiResourceCollection App\Http\Resources\Api\ResortResource
     * @apiResourceModel App\Models\Resort with=roomTypes,amenities,transfers
     */
    public function search(SearchRequest $request): JsonResponse
    {
        try {
            $searchParams = $request->validated();
            
            $results = $this->resortService->searchResorts($searchParams);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'resorts' => ResortResource::collection($results['resorts']),
                    'pagination' => $results['pagination'],
                    'filters' => [
                        'applied' => $searchParams,
                        'available' => $results['available_filters'],
                    ],
                    'summary' => [
                        'total_resorts' => $results['pagination']['total'],
                        'search_duration_ms' => $results['search_duration_ms'],
                        'currency' => 'USD',
                    ],
                ],
                'message' => 'Search completed successfully.',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching resorts.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get Popular Destinations
     * 
     * Returns a list of popular destinations with resort counts.
     */
    public function popularDestinations(): JsonResponse
    {
        try {
            $destinations = $this->resortService->getPopularDestinations();
            
            return response()->json([
                'success' => true,
                'data' => $destinations,
                'message' => 'Popular destinations retrieved successfully.',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching destinations.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get Search Suggestions
     * 
     * Returns search suggestions based on partial input for resort names, locations, or islands.
     */
    public function suggestions(): JsonResponse
    {
        try {
            $query = request()->input('q', '');
            
            if (strlen($query) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Query too short for suggestions.',
                ]);
            }
            
            $suggestions = $this->resortService->getSearchSuggestions($query);
            
            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'message' => 'Suggestions retrieved successfully.',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching suggestions.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get Available Search Filters
     * 
     * Returns all available filters for resort search including amenities, locations, price ranges, etc.
     */
    public function getFilters(): JsonResponse
    {
        try {
            $filters = $this->resortService->getSearchFilters();
            
            return response()->json([
                'success' => true,
                'data' => $filters,
                'message' => 'Search filters retrieved successfully.',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching filters.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
