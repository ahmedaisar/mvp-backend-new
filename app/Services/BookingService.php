<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GuestProfile;
use App\Models\Inventory;
use App\Models\Promotion;
use App\Models\RatePlan;
use App\Models\SeasonalRate;
use App\Models\SiteSetting;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class BookingService
{
    /**
     * Search for available resorts and rooms
     */
    public function searchAvailability($criteria)
    {
        $checkIn = Carbon::parse($criteria['check_in']);
        $checkOut = Carbon::parse($criteria['check_out']);
        $adults = $criteria['adults'] ?? 2;
        $children = $criteria['children'] ?? 0;
        $resortIds = $criteria['resort_ids'] ?? null;

        $query = RatePlan::with([
            'roomType.resort.amenities',
            'roomType.amenities',
            'seasonalRates' => function ($q) use ($checkIn, $checkOut) {
                $q->forDateRange($checkIn, $checkOut);
            }
        ])
        ->whereHas('roomType', function ($q) use ($adults, $children, $resortIds) {
            $q->active()
              ->byCapacity($adults, $children);
            
            if ($resortIds) {
                $q->whereIn('resort_id', $resortIds);
            }
            
            $q->whereHas('resort', function ($rq) {
                $rq->active();
            });
        })
        ->active();

        $ratePlans = $query->get();
        $available = [];

        foreach ($ratePlans as $ratePlan) {
            if ($ratePlan->isAvailableForDates($checkIn, $checkOut)) {
                $totalPrice = SeasonalRate::calculateTotalForPeriod(
                    $ratePlan->id,
                    $checkIn,
                    $checkOut
                );

                $available[] = [
                    'rate_plan' => $ratePlan,
                    'room_type' => $ratePlan->roomType,
                    'resort' => $ratePlan->roomType->resort,
                    'total_price' => $totalPrice,
                    'nightly_rates' => $this->getNightlyRates($ratePlan->id, $checkIn, $checkOut),
                ];
            }
        }

        return collect($available)->sortBy('total_price');
    }

    /**
     * Get nightly rates for a date range
     */
    protected function getNightlyRates($ratePlanId, $checkIn, $checkOut)
    {
        $rates = [];
        $period = Carbon::parse($checkIn)->toPeriod($checkOut, 1, 'day');

        foreach ($period as $date) {
            if ($date->eq(Carbon::parse($checkOut))) {
                break; // Exclude checkout date
            }

            $rate = SeasonalRate::where('rate_plan_id', $ratePlanId)
                ->forDate($date)
                ->first();

            $rates[] = [
                'date' => $date->toDateString(),
                'price' => $rate ? $rate->nightly_price : 0,
                'min_stay' => $rate ? $rate->min_stay : 1,
                'max_stay' => $rate ? $rate->max_stay : null,
            ];
        }

        return $rates;
    }

    /**
     * Create a new booking
     */
    public function createBooking($data)
    {
        return DB::transaction(function () use ($data) {
            // Validate availability
            if (!$this->validateAvailability($data)) {
                throw new Exception('Room is not available for the selected dates.');
            }

            // Create or find guest profile
            $guestProfile = $this->createOrUpdateGuestProfile($data['guest']);

            // Create booking
            $booking = new Booking();
            $booking->fill([
                'user_id' => auth()->id(),
                'guest_profile_id' => $guestProfile->id,
                'resort_id' => $data['resort_id'],
                'room_type_id' => $data['room_type_id'],
                'rate_plan_id' => $data['rate_plan_id'],
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'adults' => $data['adults'] ?? 2,
                'children' => $data['children'] ?? 0,
                'special_requests' => $data['special_requests'] ?? null,
                'transfer_id' => $data['transfer_id'] ?? null,
                'currency_rate_usd' => SiteSetting::getValue('currency_rates.USD_TO_MVR', 15.42),
                'status' => 'pending',
            ]);

            // Apply promotion if provided
            if (!empty($data['promo_code'])) {
                $promotion = $this->validateAndApplyPromotion($data['promo_code'], $booking);
                if ($promotion) {
                    $booking->promotion_id = $promotion->id;
                }
            }

            // Calculate total price
            $booking->calculateTotalPrice();
            $booking->save();

            // Log the booking creation
            AuditLog::log('booking_created', $booking, null, $booking->toArray());

            return $booking->load([
                'guestProfile',
                'resort',
                'roomType',
                'ratePlan',
                'transfer',
                'promotion',
                'bookingItems'
            ]);
        });
    }

    /**
     * Validate room availability for booking dates
     */
    protected function validateAvailability($data)
    {
        $ratePlan = RatePlan::find($data['rate_plan_id']);
        
        if (!$ratePlan || !$ratePlan->active) {
            return false;
        }

        return Inventory::checkAvailability(
            $data['rate_plan_id'],
            $data['check_in'],
            $data['check_out'],
            1
        );
    }

    /**
     * Create or update guest profile
     */
    protected function createOrUpdateGuestProfile($guestData)
    {
        return GuestProfile::updateOrCreate(
            ['email' => $guestData['email']],
            [
                'full_name' => $guestData['full_name'],
                'phone' => $guestData['phone'] ?? null,
                'country' => $guestData['country'] ?? null,
                'date_of_birth' => $guestData['date_of_birth'] ?? null,
                'gender' => $guestData['gender'] ?? null,
                'preferences' => $guestData['preferences'] ?? null,
            ]
        );
    }

    /**
     * Validate and apply promotion code
     */
    protected function validateAndApplyPromotion($promoCode, $booking)
    {
        $promotion = Promotion::active()
            ->byCode($promoCode)
            ->first();

        if (!$promotion) {
            return null;
        }

        $subtotal = SeasonalRate::calculateTotalForPeriod(
            $booking->rate_plan_id,
            $booking->check_in,
            $booking->check_out
        );

        if ($promotion->isValid($subtotal, $booking->rate_plan_id)) {
            return $promotion;
        }

        return null;
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking($bookingId, $reason = null)
    {
        return DB::transaction(function () use ($bookingId, $reason) {
            $booking = Booking::findOrFail($bookingId);

            if (!$booking->can_cancel) {
                throw new Exception('This booking cannot be cancelled.');
            }

            $oldStatus = $booking->status;
            $booking->cancel($reason);

            // Log the cancellation
            AuditLog::log('booking_cancelled', $booking, 
                ['status' => $oldStatus],
                ['status' => 'cancelled', 'cancellation_reason' => $reason]
            );

            return $booking;
        });
    }

    /**
     * Confirm a booking (usually after payment)
     */
    public function confirmBooking($bookingId)
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::findOrFail($bookingId);

            if ($booking->status !== 'pending') {
                throw new Exception('Only pending bookings can be confirmed.');
            }

            $oldStatus = $booking->status;
            $booking->confirm();

            // Log the confirmation
            AuditLog::log('booking_confirmed', $booking,
                ['status' => $oldStatus],
                ['status' => 'confirmed']
            );

            return $booking;
        });
    }

    /**
     * Get booking statistics for dashboard
     */
    public function getBookingStats($resortId = null, $period = '30_days')
    {
        $query = Booking::query();

        if ($resortId) {
            $query->where('resort_id', $resortId);
        }

        $dateRange = $this->getDateRangeForPeriod($period);
        $query->whereBetween('created_at', $dateRange);

        return [
            'total_bookings' => $query->count(),
            'confirmed_bookings' => $query->where('status', 'confirmed')->count(),
            'cancelled_bookings' => $query->where('status', 'cancelled')->count(),
            'pending_bookings' => $query->where('status', 'pending')->count(),
            'total_revenue' => $query->where('status', 'confirmed')->sum('total_price_usd'),
            'average_booking_value' => $query->where('status', 'confirmed')->avg('total_price_usd'),
            'occupancy_rate' => $this->calculateOccupancyRate($resortId, $dateRange),
        ];
    }

    /**
     * Calculate occupancy rate
     */
    protected function calculateOccupancyRate($resortId, $dateRange)
    {
        // This is a simplified calculation
        // In a real implementation, you'd calculate based on total available rooms
        $confirmedNights = Booking::query()
            ->when($resortId, fn($q) => $q->where('resort_id', $resortId))
            ->where('status', 'confirmed')
            ->whereBetween('check_in', $dateRange)
            ->sum('nights');

        // Assuming 100 total room nights available (this should be calculated from inventory)
        $totalAvailableNights = 100 * Carbon::parse($dateRange[0])->diffInDays($dateRange[1]);

        return $totalAvailableNights > 0 ? round(($confirmedNights / $totalAvailableNights) * 100, 2) : 0;
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
}
