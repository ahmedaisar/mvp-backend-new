<?php

namespace App\Services;

use App\Models\Resort;
use App\Models\RoomType;
use App\Models\RatePlan;
use App\Models\SeasonalRate;
use App\Models\Inventory;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class ResortService
{
    /**
     * Get resort details with availability for date range
     */
    public function getResortWithAvailability($resortId, $checkIn = null, $checkOut = null, $adults = 2, $children = 0)
    {
        $resort = Resort::with([
            'amenities',
            'roomTypes' => function ($query) use ($adults, $children) {
                $query->active()->byCapacity($adults, $children);
            },
            'roomTypes.amenities',
            'roomTypes.ratePlans' => function ($query) {
                $query->active();
            }
        ])->findOrFail($resortId);

        if ($checkIn && $checkOut) {
            // Filter room types by availability
            $resort->roomTypes = $resort->roomTypes->filter(function ($roomType) use ($checkIn, $checkOut) {
                return $roomType->ratePlans->some(function ($ratePlan) use ($checkIn, $checkOut) {
                    return $ratePlan->isAvailableForDates($checkIn, $checkOut);
                });
            });

            // Add pricing information
            foreach ($resort->roomTypes as $roomType) {
                foreach ($roomType->ratePlans as $ratePlan) {
                    $ratePlan->total_price = SeasonalRate::calculateTotalForPeriod(
                        $ratePlan->id,
                        $checkIn,
                        $checkOut
                    );
                }
            }
        }

        return $resort;
    }

    /**
     * Search resorts with filters
     */
    public function searchResorts($filters = [])
    {
        $startTime = microtime(true);
        
        $query = Resort::with(['amenities', 'roomTypes.ratePlans'])
            ->active();

        // Apply filters
        if (!empty($filters['amenities'])) {
            $query->whereHas('amenities', function ($q) use ($filters) {
                $q->whereIn('amenities.id', $filters['amenities']);
            });
        }

        if (!empty($filters['location'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('location', 'like', "%{$filters['location']}%")
                  ->orWhere('island', 'like', "%{$filters['location']}%")
                  ->orWhere('name', 'like', "%{$filters['location']}%");
            });
        }

        if (!empty($filters['island'])) {
            $query->where('island', 'like', "%{$filters['island']}%");
        }

        if (!empty($filters['resort_type'])) {
            $query->where('resort_type', $filters['resort_type']);
        }

        if (!empty($filters['rating_min'])) {
            $query->where('star_rating', '>=', $filters['rating_min']);
        }

        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $query->whereHas('roomTypes.ratePlans.seasonalRates', function ($q) use ($filters) {
                if (!empty($filters['price_min'])) {
                    $q->where('nightly_price', '>=', $filters['price_min']);
                }
                if (!empty($filters['price_max'])) {
                    $q->where('nightly_price', '<=', $filters['price_max']);
                }
            });
        }

        // Availability filter
        if (!empty($filters['check_in']) && !empty($filters['check_out'])) {
            $query->whereHas('roomTypes.ratePlans', function ($q) use ($filters) {
                $q->whereHas('inventory', function ($iq) use ($filters) {
                    $iq->whereBetween('date', [$filters['check_in'], $filters['check_out']])
                      ->where('available_rooms', '>', 0);
                });
            });
        }

        // Guests filter
        if (!empty($filters['adults']) || !empty($filters['children'])) {
            $totalGuests = ($filters['adults'] ?? 0) + ($filters['children'] ?? 0);
            $query->whereHas('roomTypes', function ($q) use ($totalGuests) {
                $q->where('max_occupancy', '>=', $totalGuests);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';

        switch ($sortBy) {
            case 'rating':
                $query->orderBy('star_rating', $sortDirection);
                break;
            case 'price':
                // This is simplified - in reality you'd join with rates table
                $query->orderBy('name', $sortDirection);
                break;
            default:
                $query->orderBy('name', $sortDirection);
        }

        $paginatedResults = $query->paginate($filters['per_page'] ?? 12);
        
        $endTime = microtime(true);
        $searchDuration = round(($endTime - $startTime) * 1000, 2);

        return [
            'resorts' => $paginatedResults->items(),
            'pagination' => [
                'current_page' => $paginatedResults->currentPage(),
                'total' => $paginatedResults->total(),
                'per_page' => $paginatedResults->perPage(),
                'last_page' => $paginatedResults->lastPage(),
                'from' => $paginatedResults->firstItem(),
                'to' => $paginatedResults->lastItem(),
                'has_more' => $paginatedResults->hasMorePages(),
            ],
            'available_filters' => $this->getSearchFilters(),
            'search_duration_ms' => $searchDuration,
        ];
    }

    /**
     * Get resort dashboard statistics
     */
    public function getResortDashboardStats($resortId, $period = '30_days')
    {
        $dateRange = $this->getDateRangeForPeriod($period);
        
        $bookingStats = Booking::where('resort_id', $resortId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status = "confirmed" THEN total_price_usd ELSE 0 END) as total_revenue,
                AVG(CASE WHEN status = "confirmed" THEN total_price_usd ELSE NULL END) as avg_booking_value,
                SUM(CASE WHEN status = "confirmed" THEN nights ELSE 0 END) as total_room_nights
            ')
            ->first();

        $occupancyRate = $this->calculateResortOccupancyRate($resortId, $dateRange);
        $averageDailyRate = $this->calculateResortADR($resortId, $dateRange);

        return [
            'period' => $period,
            'total_bookings' => $bookingStats->total_bookings ?? 0,
            'confirmed_bookings' => $bookingStats->confirmed_bookings ?? 0,
            'cancelled_bookings' => $bookingStats->cancelled_bookings ?? 0,
            'cancellation_rate' => $bookingStats->total_bookings > 0 
                ? round(($bookingStats->cancelled_bookings / $bookingStats->total_bookings) * 100, 2)
                : 0,
            'total_revenue' => $bookingStats->total_revenue ?? 0,
            'average_booking_value' => $bookingStats->avg_booking_value ?? 0,
            'total_room_nights' => $bookingStats->total_room_nights ?? 0,
            'occupancy_rate' => $occupancyRate,
            'average_daily_rate' => $averageDailyRate,
            'revenue_per_available_room' => ($occupancyRate / 100) * $averageDailyRate,
        ];
    }

    /**
     * Calculate resort occupancy rate
     */
    protected function calculateResortOccupancyRate($resortId, $dateRange)
    {
        $ratePlans = RatePlan::whereHas('roomType', function ($q) use ($resortId) {
            $q->where('resort_id', $resortId);
        })->pluck('id');

        if ($ratePlans->isEmpty()) {
            return 0;
        }

        $totalAvailableNights = Inventory::whereIn('rate_plan_id', $ratePlans)
            ->whereBetween('date', $dateRange)
            ->sum('total_rooms');

        $bookedNights = Inventory::whereIn('rate_plan_id', $ratePlans)
            ->whereBetween('date', $dateRange)
            ->sum('booked_rooms');

        return $totalAvailableNights > 0 
            ? round(($bookedNights / $totalAvailableNights) * 100, 2)
            : 0;
    }

    /**
     * Calculate resort Average Daily Rate
     */
    protected function calculateResortADR($resortId, $dateRange)
    {
        $ratePlans = RatePlan::whereHas('roomType', function ($q) use ($resortId) {
            $q->where('resort_id', $resortId);
        })->pluck('id');

        if ($ratePlans->isEmpty()) {
            return 0;
        }

        return SeasonalRate::whereIn('rate_plan_id', $ratePlans)
            ->whereBetween('valid_from', $dateRange)
            ->avg('nightly_price') ?? 0;
    }

    /**
     * Create a new resort
     */
    public function createResort($data)
    {
        return DB::transaction(function () use ($data) {
            $resort = Resort::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'],
                'island' => $data['island'],
                'atoll' => $data['atoll'],
                'coordinates' => $data['coordinates'] ?? null,
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'],
                'star_rating' => $data['star_rating'],
                'resort_type' => $data['resort_type'],
                'check_in_time' => $data['check_in_time'] ?? '14:00',
                'check_out_time' => $data['check_out_time'] ?? '12:00',
                'active' => $data['active'] ?? true,
            ]);

            // Attach amenities if provided
            if (!empty($data['amenity_ids'])) {
                $resort->amenities()->sync($data['amenity_ids']);
            }

            // Log the creation
            AuditLog::log('resort_created', $resort, null, $resort->toArray());

            return $resort->load('amenities');
        });
    }

    /**
     * Update resort information
     */
    public function updateResort($resortId, $data)
    {
        return DB::transaction(function () use ($resortId, $data) {
            $resort = Resort::findOrFail($resortId);
            $oldData = $resort->toArray();

            $resort->update($data);

            // Update amenities if provided
            if (isset($data['amenity_ids'])) {
                $resort->amenities()->sync($data['amenity_ids']);
            }

            // Log the update
            AuditLog::log('resort_updated', $resort, $oldData, $resort->fresh()->toArray());

            return $resort->load('amenities');
        });
    }

    /**
     * Create a new room type for a resort
     */
    public function createRoomType($resortId, $data)
    {
        return DB::transaction(function () use ($resortId, $data) {
            $roomType = RoomType::create([
                'resort_id' => $resortId,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'],
                'size_sqm' => $data['size_sqm'] ?? null,
                'max_adults' => $data['max_adults'] ?? 2,
                'max_children' => $data['max_children'] ?? 2,
                'max_occupancy' => $data['max_occupancy'] ?? 4,
                'bed_configuration' => $data['bed_configuration'] ?? null,
                'view_type' => $data['view_type'] ?? null,
                'total_rooms' => $data['total_rooms'] ?? 10,
                'active' => $data['active'] ?? true,
            ]);

            // Attach amenities if provided
            if (!empty($data['amenity_ids'])) {
                $roomType->amenities()->sync($data['amenity_ids']);
            }

            // Log the creation
            AuditLog::log('room_type_created', $roomType, null, $roomType->toArray());

            return $roomType->load('amenities');
        });
    }

    /**
     * Create a new rate plan for a room type
     */
    public function createRatePlan($roomTypeId, $data)
    {
        return DB::transaction(function () use ($roomTypeId, $data) {
            $ratePlan = RatePlan::create([
                'room_type_id' => $roomTypeId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'meal_plan' => $data['meal_plan'] ?? 'room_only',
                'cancellation_policy' => $data['cancellation_policy'] ?? 'flexible',
                'min_stay' => $data['min_stay'] ?? 1,
                'max_stay' => $data['max_stay'] ?? null,
                'advance_booking_days' => $data['advance_booking_days'] ?? 0,
                'total_rooms' => $data['total_rooms'] ?? 10,
                'active' => $data['active'] ?? true,
            ]);

            // Create default seasonal rate if base price provided
            if (!empty($data['base_price'])) {
                SeasonalRate::create([
                    'rate_plan_id' => $ratePlan->id,
                    'name' => 'Base Rate',
                    'nightly_price' => $data['base_price'],
                    'valid_from' => now()->toDateString(),
                    'valid_to' => now()->addYear()->toDateString(),
                    'min_stay' => $data['min_stay'] ?? 1,
                    'max_stay' => $data['max_stay'] ?? null,
                    'active' => true,
                ]);
            }

            // Log the creation
            AuditLog::log('rate_plan_created', $ratePlan, null, $ratePlan->toArray());

            return $ratePlan->load('seasonalRates');
        });
    }

    /**
     * Bulk update inventory for a resort
     */
    public function bulkUpdateResortInventory($resortId, $updates)
    {
        return DB::transaction(function () use ($resortId, $updates) {
            $ratePlans = RatePlan::whereHas('roomType', function ($q) use ($resortId) {
                $q->where('resort_id', $resortId);
            })->pluck('id');

            $updated = 0;

            foreach ($updates as $update) {
                if (!in_array($update['rate_plan_id'], $ratePlans->toArray())) {
                    continue; // Skip if rate plan doesn't belong to this resort
                }

                Inventory::updateOrCreate([
                    'rate_plan_id' => $update['rate_plan_id'],
                    'date' => $update['date'],
                ], [
                    'total_rooms' => $update['total_rooms'],
                    'available_rooms' => $update['available_rooms'] ?? $update['total_rooms'],
                    'reserved_rooms' => $update['reserved_rooms'] ?? 0,
                    'booked_rooms' => $update['booked_rooms'] ?? 0,
                ]);

                $updated++;
            }

            return $updated;
        });
    }

    /**
     * Get resort performance comparison
     */
    public function getResortPerformanceComparison($resortIds, $period = '30_days')
    {
        $dateRange = $this->getDateRangeForPeriod($period);
        $comparison = [];

        foreach ($resortIds as $resortId) {
            $resort = Resort::find($resortId);
            if (!$resort) {
                continue;
            }

            $stats = $this->getResortDashboardStats($resortId, $period);
            
            $comparison[] = [
                'resort' => $resort,
                'stats' => $stats,
            ];
        }

        return collect($comparison)->sortByDesc('stats.total_revenue');
    }

    /**
     * Get popular amenities analysis
     */
    public function getPopularAmenitiesAnalysis($resortId = null, $period = '30_days')
    {
        $dateRange = $this->getDateRangeForPeriod($period);
        
        $query = Amenity::withCount(['resorts as resort_bookings' => function ($q) use ($resortId, $dateRange) {
            $q->whereHas('bookings', function ($bq) use ($dateRange) {
                $bq->whereBetween('created_at', $dateRange)
                  ->where('status', 'confirmed');
            });
            
            if ($resortId) {
                $q->where('resorts.id', $resortId);
            }
        }]);

        return $query->orderByDesc('resort_bookings')
            ->take(20)
            ->get()
            ->map(function ($amenity) {
                return [
                    'amenity' => $amenity,
                    'booking_count' => $amenity->resort_bookings,
                ];
            });
    }

    /**
     * Get date range for period
     */
    protected function getDateRangeForPeriod($period)
    {
        $end = now();
        
        switch ($period) {
            case '7_days':
                $start = $end->copy()->subDays(7);
                break;
            case '30_days':
                $start = $end->copy()->subDays(30);
                break;
            case '90_days':
                $start = $end->copy()->subDays(90);
                break;
            case '1_year':
                $start = $end->copy()->subYear();
                break;
            default:
                $start = $end->copy()->subDays(30);
        }

        return [$start, $end];
    }

    /**
     * Archive/deactivate a resort
     */
    public function archiveResort($resortId, $reason = null)
    {
        return DB::transaction(function () use ($resortId, $reason) {
            $resort = Resort::findOrFail($resortId);
            
            // Check for active bookings
            $activeBookings = Booking::where('resort_id', $resortId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('check_out', '>=', now())
                ->count();

            if ($activeBookings > 0) {
                throw new Exception("Cannot archive resort with {$activeBookings} active bookings.");
            }

            $oldData = $resort->toArray();
            $resort->update(['active' => false]);

            // Deactivate all room types and rate plans
            RoomType::where('resort_id', $resortId)->update(['active' => false]);
            RatePlan::whereHas('roomType', function ($q) use ($resortId) {
                $q->where('resort_id', $resortId);
            })->update(['active' => false]);

            // Log the archival
            AuditLog::log('resort_archived', $resort, $oldData, [
                'active' => false,
                'archived_reason' => $reason,
            ]);

            return $resort;
        });
    }

    /**
     * Get resort availability calendar
     */
    public function getResortAvailabilityCalendar($resortId, $startDate, $endDate)
    {
        $ratePlans = RatePlan::whereHas('roomType', function ($q) use ($resortId) {
            $q->where('resort_id', $resortId)->active();
        })->active()->with('roomType')->get();

        $calendar = [];
        $period = Carbon::parse($startDate)->toPeriod($endDate, 1, 'day');

        foreach ($period as $date) {
            $dateString = $date->toDateString();
            $dayData = [
                'date' => $dateString,
                'day_of_week' => $date->format('l'),
                'rate_plans' => [],
                'total_available' => 0,
                'total_booked' => 0,
            ];

            foreach ($ratePlans as $ratePlan) {
                $inventory = Inventory::where('rate_plan_id', $ratePlan->id)
                    ->where('start_date', '<=', $dateString)
                    ->where('end_date', '>=', $dateString)
                    ->first();

                $available = $inventory ? $inventory->available_rooms : $ratePlan->total_rooms;
                $booked = $inventory ? $inventory->booked_rooms : 0;

                $dayData['rate_plans'][] = [
                    'rate_plan' => $ratePlan,
                    'room_type' => $ratePlan->roomType,
                    'available_rooms' => $available,
                    'booked_rooms' => $booked,
                    'total_rooms' => $ratePlan->total_rooms,
                ];

                $dayData['total_available'] += $available;
                $dayData['total_booked'] += $booked;
            }

            $calendar[] = $dayData;
        }

        return collect($calendar);
    }

    /**
     * Get popular destinations with resort counts
     */
    public function getPopularDestinations($limit = 10)
    {
        $destinations = Resort::select('location', DB::raw('count(*) as resort_count'))
            ->where('status', 'active')
            ->groupBy('location')
            ->orderByDesc('resort_count')
            ->limit($limit)
            ->get();

        return $destinations->map(function ($destination) {
            return [
                'location' => $destination->location,
                'resort_count' => $destination->resort_count,
                'slug' => Str::slug($destination->location),
            ];
        });
    }

    /**
     * Get search suggestions based on query
     */
    public function getSearchSuggestions($query, $limit = 10)
    {
        $suggestions = [];

        // Search resort names
        $resorts = Resort::where('name', 'LIKE', "%{$query}%")
            ->where('status', 'active')
            ->select('id', 'name', 'location')
            ->limit($limit)
            ->get();

        foreach ($resorts as $resort) {
            $suggestions[] = [
                'type' => 'resort',
                'id' => $resort->id,
                'name' => $resort->name,
                'location' => $resort->location,
                'suggestion' => $resort->name . ', ' . $resort->location,
            ];
        }

        // Search locations
        $locations = Resort::where('location', 'LIKE', "%{$query}%")
            ->where('status', 'active')
            ->select('location', DB::raw('count(*) as resort_count'))
            ->groupBy('location')
            ->limit($limit - count($suggestions))
            ->get();

        foreach ($locations as $location) {
            $suggestions[] = [
                'type' => 'location',
                'name' => $location->location,
                'resort_count' => $location->resort_count,
                'suggestion' => $location->location,
            ];
        }

        return collect($suggestions);
    }

    /**
     * Get available search filters
     */
    public function getSearchFilters()
    {
        return [
            'amenities' => Amenity::select('id', 'name', 'icon')->get(),
            'locations' => Resort::select('location')
                ->where('status', 'active')
                ->distinct()
                ->pluck('location')
                ->values(),
            'price_ranges' => [
                ['min' => 0, 'max' => 500, 'label' => 'Under $500'],
                ['min' => 500, 'max' => 1000, 'label' => '$500 - $1000'],
                ['min' => 1000, 'max' => 2000, 'label' => '$1000 - $2000'],
                ['min' => 2000, 'max' => null, 'label' => 'Over $2000'],
            ],
            'star_ratings' => [3, 4, 5],
            'meal_plans' => [
                'BB' => 'Bed & Breakfast',
                'HB' => 'Half Board',
                'FB' => 'Full Board',
                'AI' => 'All Inclusive',
            ],
            'transfer_types' => [
                'seaplane' => 'Seaplane',
                'speedboat' => 'Speedboat',
                'domestic_flight' => 'Domestic Flight + Speedboat',
            ]
        ];
    }
}
