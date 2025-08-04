<?php

namespace App\Http\Controllers;

use App\Models\Resort;
use App\Models\RoomType;
use App\Models\RatePlan;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\GuestProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class AdvancedSearchController extends Controller
{
    /**
     * Advanced resort search with multiple filters
     */
    public function searchResorts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:100',
            'check_in' => 'nullable|date|after:today',
            'check_out' => 'nullable|date|after:check_in',
            'adults' => 'nullable|integer|min:1|max:10',
            'children' => 'nullable|integer|min:0|max:8',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'amenities' => 'nullable|array',
            'amenities.*' => 'exists:amenities,id',
            'room_type' => 'nullable|string|max:50',
            'rating_min' => 'nullable|numeric|min:1|max:5',
            'sort_by' => 'nullable|in:price_asc,price_desc,rating_desc,distance,popularity',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = $request->input('query');
            $location = $request->input('location');
            $checkIn = $request->input('check_in');
            $checkOut = $request->input('check_out');
            $adults = $request->input('adults', 2);
            $children = $request->input('children', 0);
            $priceMin = $request->input('price_min');
            $priceMax = $request->input('price_max');
            $amenities = $request->input('amenities', []);
            $roomType = $request->input('room_type');
            $ratingMin = $request->input('rating_min');
            $sortBy = $request->input('sort_by', 'popularity');
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            // Build cache key
            $cacheKey = 'resort_search_' . md5(serialize($request->all()));
            $cacheDuration = now()->addMinutes(15);

            $results = Cache::remember($cacheKey, $cacheDuration, function () use (
                $query, $location, $checkIn, $checkOut, $adults, $children,
                $priceMin, $priceMax, $amenities, $roomType, $ratingMin,
                $sortBy, $page, $limit
            ) {
                // Base query
                $resortQuery = Resort::select([
                    'resorts.*',
                    DB::raw('AVG(rate_plans.base_price_usd) as avg_price'),
                    DB::raw('MIN(rate_plans.base_price_usd) as min_price'),
                    DB::raw('MAX(rate_plans.base_price_usd) as max_price'),
                    DB::raw('COUNT(DISTINCT bookings.id) as booking_count'),
                    DB::raw('AVG(guest_profiles.rating) as avg_rating'),
                ])
                ->leftJoin('rate_plans', 'resorts.id', '=', 'rate_plans.resort_id')
                ->leftJoin('room_types', 'rate_plans.room_type_id', '=', 'room_types.id')
                ->leftJoin('bookings', 'resorts.id', '=', 'bookings.resort_id')
                ->leftJoin('guest_profiles', 'bookings.guest_id', '=', 'guest_profiles.id')
                ->where('resorts.status', 'active');

                // Text search
                if ($query) {
                    $resortQuery->where(function($q) use ($query) {
                        $q->where('resorts.name', 'LIKE', "%{$query}%")
                          ->orWhere('resorts.description', 'LIKE', "%{$query}%")
                          ->orWhere('resorts.location', 'LIKE', "%{$query}%")
                          ->orWhere('resorts.tags', 'LIKE', "%{$query}%");
                    });
                }

                // Location filter
                if ($location) {
                    $resortQuery->where('resorts.location', 'LIKE', "%{$location}%");
                }

                // Room type filter
                if ($roomType) {
                    $resortQuery->where('room_types.name', 'LIKE', "%{$roomType}%");
                }

                // Price filters
                if ($priceMin) {
                    $resortQuery->having('min_price', '>=', $priceMin);
                }
                if ($priceMax) {
                    $resortQuery->having('max_price', '<=', $priceMax);
                }

                // Rating filter
                if ($ratingMin) {
                    $resortQuery->having('avg_rating', '>=', $ratingMin);
                }

                // Availability filter
                if ($checkIn && $checkOut) {
                    $resortQuery->whereNotExists(function($q) use ($checkIn, $checkOut) {
                        $q->select(DB::raw(1))
                          ->from('bookings')
                          ->whereRaw('bookings.resort_id = resorts.id')
                          ->where('bookings.status', 'confirmed')
                          ->where(function($dateQ) use ($checkIn, $checkOut) {
                              $dateQ->whereBetween('check_in', [$checkIn, $checkOut])
                                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                                    ->orWhere(function($overlapQ) use ($checkIn, $checkOut) {
                                        $overlapQ->where('check_in', '<=', $checkIn)
                                                ->where('check_out', '>=', $checkOut);
                                    });
                          });
                    });
                }

                $resortQuery->groupBy('resorts.id');

                // Amenities filter
                if (!empty($amenities)) {
                    $resortQuery->whereHas('amenities', function($q) use ($amenities) {
                        $q->whereIn('amenities.id', $amenities);
                    }, '>=', count($amenities));
                }

                // Sorting
                switch ($sortBy) {
                    case 'price_asc':
                        $resortQuery->orderBy('min_price', 'asc');
                        break;
                    case 'price_desc':
                        $resortQuery->orderBy('max_price', 'desc');
                        break;
                    case 'rating_desc':
                        $resortQuery->orderBy('avg_rating', 'desc');
                        break;
                    case 'distance':
                        // Would need coordinates for proper distance sorting
                        $resortQuery->orderBy('resorts.location', 'asc');
                        break;
                    case 'popularity':
                    default:
                        $resortQuery->orderBy('booking_count', 'desc')
                                    ->orderBy('avg_rating', 'desc');
                        break;
                }

                // Get total count before pagination
                $totalCount = $resortQuery->count(DB::raw('DISTINCT resorts.id'));

                // Pagination
                $offset = ($page - 1) * $limit;
                $resorts = $resortQuery->skip($offset)->take($limit)->get();

                // Load relationships
                $resorts->load([
                    'amenities',
                    'roomTypes',
                    'ratePlans' => function($query) use ($checkIn, $checkOut) {
                        if ($checkIn && $checkOut) {
                            $query->whereDoesntHave('bookings', function($q) use ($checkIn, $checkOut) {
                                $q->where('status', 'confirmed')
                                  ->where(function($dateQ) use ($checkIn, $checkOut) {
                                      $dateQ->whereBetween('check_in', [$checkIn, $checkOut])
                                            ->orWhereBetween('check_out', [$checkIn, $checkOut]);
                                  });
                            });
                        }
                    }
                ]);

                return [
                    'resorts' => $resorts,
                    'total_count' => $totalCount,
                    'total_pages' => ceil($totalCount / $limit),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'resorts' => $results['resorts'],
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => $results['total_pages'],
                        'total_count' => $results['total_count'],
                        'per_page' => $limit,
                        'has_next' => $page < $results['total_pages'],
                        'has_previous' => $page > 1,
                    ],
                    'filters_applied' => array_filter([
                        'query' => $query,
                        'location' => $location,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'adults' => $adults,
                        'children' => $children,
                        'price_range' => $priceMin || $priceMax ? [$priceMin, $priceMax] : null,
                        'amenities' => $amenities,
                        'room_type' => $roomType,
                        'rating_min' => $ratingMin,
                        'sort_by' => $sortBy,
                    ]),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error performing search: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get search suggestions for autocomplete
     */
    public function getSearchSuggestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:50',
            'type' => 'nullable|in:resorts,locations,amenities,all',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = $request->input('query');
            $type = $request->input('type', 'all');
            $limit = $request->input('limit', 10);

            $cacheKey = "search_suggestions_{$query}_{$type}_{$limit}";
            $cacheDuration = now()->addHours(1);

            $suggestions = Cache::remember($cacheKey, $cacheDuration, function () use ($query, $type, $limit) {
                $results = [];

                if ($type === 'all' || $type === 'resorts') {
                    $resorts = Resort::select('id', 'name', 'location')
                        ->where('name', 'LIKE', "%{$query}%")
                        ->where('status', 'active')
                        ->limit($limit)
                        ->get()
                        ->map(function($resort) {
                            return [
                                'type' => 'resort',
                                'id' => $resort->id,
                                'title' => $resort->name,
                                'subtitle' => $resort->location,
                                'url' => "/resorts/{$resort->id}",
                            ];
                        });

                    $results = array_merge($results, $resorts->toArray());
                }

                if ($type === 'all' || $type === 'locations') {
                    $locations = Resort::select('location')
                        ->where('location', 'LIKE', "%{$query}%")
                        ->where('status', 'active')
                        ->distinct()
                        ->limit($limit)
                        ->get()
                        ->map(function($location) {
                            return [
                                'type' => 'location',
                                'id' => null,
                                'title' => $location->location,
                                'subtitle' => 'Location',
                                'url' => "/search?location=" . urlencode($location->location),
                            ];
                        });

                    $results = array_merge($results, $locations->toArray());
                }

                if ($type === 'all' || $type === 'amenities') {
                    $amenities = Amenity::select('id', 'name', 'description')
                        ->where('name', 'LIKE', "%{$query}%")
                        ->limit($limit)
                        ->get()
                        ->map(function($amenity) {
                            return [
                                'type' => 'amenity',
                                'id' => $amenity->id,
                                'title' => $amenity->name,
                                'subtitle' => $amenity->description,
                                'url' => "/search?amenities[]={$amenity->id}",
                            ];
                        });

                    $results = array_merge($results, $amenities->toArray());
                }

                // Limit final results
                return array_slice($results, 0, $limit);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'query' => $query,
                    'type' => $type,
                    'count' => count($suggestions),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting suggestions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available search filters
     */
    public function getSearchFilters(Request $request)
    {
        try {
            $cacheKey = 'search_filters';
            $cacheDuration = now()->addHours(2);

            $filters = Cache::remember($cacheKey, $cacheDuration, function () {
                return [
                    'locations' => Resort::select('location')
                        ->where('status', 'active')
                        ->distinct()
                        ->orderBy('location')
                        ->pluck('location')
                        ->filter()
                        ->values(),

                    'amenities' => Amenity::select('id', 'name', 'category')
                        ->orderBy('category')
                        ->orderBy('name')
                        ->get()
                        ->groupBy('category')
                        ->map(function($amenities) {
                            return $amenities->map(function($amenity) {
                                return [
                                    'id' => $amenity->id,
                                    'name' => $amenity->name,
                                ];
                            });
                        }),

                    'room_types' => RoomType::select('id', 'name')
                        ->distinct()
                        ->orderBy('name')
                        ->get()
                        ->map(function($roomType) {
                            return [
                                'id' => $roomType->id,
                                'name' => $roomType->name,
                            ];
                        }),

                    'price_ranges' => [
                        ['min' => 0, 'max' => 100, 'label' => '$0 - $100'],
                        ['min' => 100, 'max' => 250, 'label' => '$100 - $250'],
                        ['min' => 250, 'max' => 500, 'label' => '$250 - $500'],
                        ['min' => 500, 'max' => 1000, 'label' => '$500 - $1000'],
                        ['min' => 1000, 'max' => null, 'label' => '$1000+'],
                    ],

                    'rating_options' => [
                        ['value' => 3, 'label' => '3+ Stars'],
                        ['value' => 4, 'label' => '4+ Stars'],
                        ['value' => 4.5, 'label' => '4.5+ Stars'],
                        ['value' => 5, 'label' => '5 Stars'],
                    ],

                    'sort_options' => [
                        ['value' => 'popularity', 'label' => 'Most Popular'],
                        ['value' => 'rating_desc', 'label' => 'Highest Rated'],
                        ['value' => 'price_asc', 'label' => 'Price: Low to High'],
                        ['value' => 'price_desc', 'label' => 'Price: High to Low'],
                        ['value' => 'distance', 'label' => 'Distance'],
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $filters,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving filters: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get popular search terms
     */
    public function getPopularSearches(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $period = $request->input('period', 30); // days

            // This would typically come from search analytics/logs
            // For now, we'll return static popular searches
            $popularSearches = [
                ['term' => 'beach resort', 'count' => 1250],
                ['term' => 'luxury villa', 'count' => 980],
                ['term' => 'spa resort', 'count' => 756],
                ['term' => 'family friendly', 'count' => 623],
                ['term' => 'water sports', 'count' => 445],
                ['term' => 'honeymoon suite', 'count' => 387],
                ['term' => 'all inclusive', 'count' => 356],
                ['term' => 'private beach', 'count' => 289],
                ['term' => 'diving center', 'count' => 234],
                ['term' => 'sunset view', 'count' => 198],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'popular_searches' => array_slice($popularSearches, 0, $limit),
                    'period_days' => $period,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving popular searches: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Advanced guest search for admin
     */
    public function searchGuests(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'booking_status' => 'nullable|in:confirmed,pending,cancelled,completed',
            'registration_date_from' => 'nullable|date',
            'registration_date_to' => 'nullable|date|after_or_equal:registration_date_from',
            'has_bookings' => 'nullable|boolean',
            'sort_by' => 'nullable|in:name,email,created_at,last_booking,total_spent',
            'sort_order' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = $request->input('query');
            $email = $request->input('email');
            $phone = $request->input('phone');
            $country = $request->input('country');
            $bookingStatus = $request->input('booking_status');
            $registrationFrom = $request->input('registration_date_from');
            $registrationTo = $request->input('registration_date_to');
            $hasBookings = $request->input('has_bookings');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $guestQuery = GuestProfile::select([
                'guest_profiles.*',
                DB::raw('COUNT(DISTINCT bookings.id) as booking_count'),
                DB::raw('MAX(bookings.created_at) as last_booking_date'),
                DB::raw('SUM(CASE WHEN bookings.status = "completed" THEN bookings.total_price_usd ELSE 0 END) as total_spent'),
            ])
            ->leftJoin('bookings', 'guest_profiles.id', '=', 'bookings.guest_id');

            // Text search
            if ($query) {
                $guestQuery->where(function($q) use ($query) {
                    $q->where('guest_profiles.first_name', 'LIKE', "%{$query}%")
                      ->orWhere('guest_profiles.last_name', 'LIKE', "%{$query}%")
                      ->orWhere('guest_profiles.email', 'LIKE', "%{$query}%")
                      ->orWhere('guest_profiles.phone', 'LIKE', "%{$query}%");
                });
            }

            // Specific filters
            if ($email) {
                $guestQuery->where('guest_profiles.email', 'LIKE', "%{$email}%");
            }

            if ($phone) {
                $guestQuery->where('guest_profiles.phone', 'LIKE', "%{$phone}%");
            }

            if ($country) {
                $guestQuery->where('guest_profiles.country', $country);
            }

            if ($registrationFrom) {
                $guestQuery->where('guest_profiles.created_at', '>=', $registrationFrom);
            }

            if ($registrationTo) {
                $guestQuery->where('guest_profiles.created_at', '<=', $registrationTo);
            }

            if ($bookingStatus) {
                $guestQuery->whereHas('bookings', function($q) use ($bookingStatus) {
                    $q->where('status', $bookingStatus);
                });
            }

            if ($hasBookings !== null) {
                if ($hasBookings) {
                    $guestQuery->has('bookings');
                } else {
                    $guestQuery->doesntHave('bookings');
                }
            }

            $guestQuery->groupBy('guest_profiles.id');

            // Sorting
            switch ($sortBy) {
                case 'name':
                    $guestQuery->orderBy('guest_profiles.first_name', $sortOrder)
                              ->orderBy('guest_profiles.last_name', $sortOrder);
                    break;
                case 'email':
                    $guestQuery->orderBy('guest_profiles.email', $sortOrder);
                    break;
                case 'last_booking':
                    $guestQuery->orderBy('last_booking_date', $sortOrder);
                    break;
                case 'total_spent':
                    $guestQuery->orderBy('total_spent', $sortOrder);
                    break;
                case 'created_at':
                default:
                    $guestQuery->orderBy('guest_profiles.created_at', $sortOrder);
                    break;
            }

            // Get total count
            $totalCount = $guestQuery->count(DB::raw('DISTINCT guest_profiles.id'));

            // Pagination
            $offset = ($page - 1) * $limit;
            $guests = $guestQuery->skip($offset)->take($limit)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'guests' => $guests,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($totalCount / $limit),
                        'total_count' => $totalCount,
                        'per_page' => $limit,
                    ],
                    'filters_applied' => array_filter([
                        'query' => $query,
                        'email' => $email,
                        'phone' => $phone,
                        'country' => $country,
                        'booking_status' => $bookingStatus,
                        'registration_date_from' => $registrationFrom,
                        'registration_date_to' => $registrationTo,
                        'has_bookings' => $hasBookings,
                        'sort_by' => $sortBy,
                        'sort_order' => $sortOrder,
                    ]),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching guests: ' . $e->getMessage(),
            ], 500);
        }
    }
}
