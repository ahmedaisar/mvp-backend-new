<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\RatePlan;
use App\Models\SeasonalRate;
use App\Models\Booking;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    /**
     * Check room availability for a date range
     */
    public function checkAvailability($ratePlanId, $checkIn, $checkOut, $rooms = 1)
    {
        return Inventory::checkAvailability($ratePlanId, $checkIn, $checkOut, $rooms);
    }

    /**
     * Bulk check availability for multiple rate plans
     */
    public function bulkCheckAvailability(array $ratePlanIds, $checkIn, $checkOut, $rooms = 1)
    {
        $results = [];

        foreach ($ratePlanIds as $ratePlanId) {
            $results[$ratePlanId] = $this->checkAvailability($ratePlanId, $checkIn, $checkOut, $rooms);
        }

        return $results;
    }

    /**
     * Reserve inventory for a booking
     */
    public function reserveInventory($bookingId)
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::findOrFail($bookingId);

            if ($booking->status !== 'pending') {
                throw new Exception('Can only reserve inventory for pending bookings.');
            }

            $period = Carbon::parse($booking->check_in)->toPeriod($booking->check_out, 1, 'day');
            $reserved = [];

            foreach ($period as $date) {
                if ($date->eq(Carbon::parse($booking->check_out))) {
                    break; // Exclude checkout date
                }

                $inventory = Inventory::firstOrCreate([
                    'rate_plan_id' => $booking->rate_plan_id,
                    'date' => $date->toDateString(),
                ], [
                    'total_rooms' => $this->getDefaultRoomCount($booking->rate_plan_id),
                    'available_rooms' => $this->getDefaultRoomCount($booking->rate_plan_id),
                    'reserved_rooms' => 0,
                    'booked_rooms' => 0,
                ]);

                if ($inventory->available_rooms < 1) {
                    // Rollback any previous reservations for this booking
                    $this->releaseReservation($bookingId);
                    throw new Exception("No rooms available for {$date->toDateString()}");
                }

                $inventory->decrement('available_rooms');
                $inventory->increment('reserved_rooms');
                $reserved[] = $inventory;

                // Log the reservation
                AuditLog::log('inventory_reserved', $inventory, null, [
                    'booking_id' => $bookingId,
                    'date' => $date->toDateString(),
                    'rooms_reserved' => 1,
                ]);
            }

            return $reserved;
        });
    }

    /**
     * Release reserved inventory (e.g., when booking is cancelled or expires)
     */
    public function releaseReservation($bookingId)
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::findOrFail($bookingId);
            
            $period = Carbon::parse($booking->check_in)->toPeriod($booking->check_out, 1, 'day');
            $released = [];

            foreach ($period as $date) {
                if ($date->eq(Carbon::parse($booking->check_out))) {
                    break;
                }

                $inventory = Inventory::where([
                    'rate_plan_id' => $booking->rate_plan_id,
                    'date' => $date->toDateString(),
                ])->first();

                if ($inventory && $inventory->reserved_rooms > 0) {
                    $inventory->increment('available_rooms');
                    $inventory->decrement('reserved_rooms');
                    $released[] = $inventory;

                    // Log the release
                    AuditLog::log('inventory_released', $inventory, null, [
                        'booking_id' => $bookingId,
                        'date' => $date->toDateString(),
                        'rooms_released' => 1,
                    ]);
                }
            }

            return $released;
        });
    }

    /**
     * Confirm booking inventory (move reserved to booked)
     */
    public function confirmBookingInventory($bookingId)
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::findOrFail($bookingId);
            
            $period = Carbon::parse($booking->check_in)->toPeriod($booking->check_out, 1, 'day');
            $confirmed = [];

            foreach ($period as $date) {
                if ($date->eq(Carbon::parse($booking->check_out))) {
                    break;
                }

                $inventory = Inventory::where([
                    'rate_plan_id' => $booking->rate_plan_id,
                    'date' => $date->toDateString(),
                ])->first();

                if ($inventory && $inventory->reserved_rooms > 0) {
                    $inventory->decrement('reserved_rooms');
                    $inventory->increment('booked_rooms');
                    $confirmed[] = $inventory;

                    // Log the confirmation
                    AuditLog::log('inventory_confirmed', $inventory, null, [
                        'booking_id' => $bookingId,
                        'date' => $date->toDateString(),
                        'rooms_confirmed' => 1,
                    ]);
                }
            }

            return $confirmed;
        });
    }

    /**
     * Release booked inventory (e.g., when confirmed booking is cancelled)
     */
    public function releaseBookedInventory($bookingId)
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::findOrFail($bookingId);
            
            $period = Carbon::parse($booking->check_in)->toPeriod($booking->check_out, 1, 'day');
            $released = [];

            foreach ($period as $date) {
                if ($date->eq(Carbon::parse($booking->check_out))) {
                    break;
                }

                $inventory = Inventory::where([
                    'rate_plan_id' => $booking->rate_plan_id,
                    'date' => $date->toDateString(),
                ])->first();

                if ($inventory && $inventory->booked_rooms > 0) {
                    $inventory->increment('available_rooms');
                    $inventory->decrement('booked_rooms');
                    $released[] = $inventory;

                    // Log the release
                    AuditLog::log('inventory_released_booked', $inventory, null, [
                        'booking_id' => $bookingId,
                        'date' => $date->toDateString(),
                        'rooms_released' => 1,
                    ]);
                }
            }

            return $released;
        });
    }

    /**
     * Bulk update inventory for a rate plan
     */
    public function bulkUpdateInventory($ratePlanId, array $updates)
    {
        return DB::transaction(function () use ($ratePlanId, $updates) {
            $updated = [];

            foreach ($updates as $update) {
                $inventory = Inventory::updateOrCreate([
                    'rate_plan_id' => $ratePlanId,
                    'date' => $update['date'],
                ], [
                    'total_rooms' => $update['total_rooms'],
                    'available_rooms' => $update['available_rooms'] ?? $update['total_rooms'],
                    'reserved_rooms' => $update['reserved_rooms'] ?? 0,
                    'booked_rooms' => $update['booked_rooms'] ?? 0,
                ]);

                $updated[] = $inventory;

                // Log the update
                AuditLog::log('inventory_bulk_updated', $inventory, null, $update);
            }

            return collect($updated);
        });
    }

    /**
     * Get inventory calendar for a rate plan
     */
    public function getInventoryCalendar($ratePlanId, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $inventories = Inventory::where('rate_plan_id', $ratePlanId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $calendar = [];
        $period = $start->toPeriod($end, 1, 'day');

        foreach ($period as $date) {
            $dateString = $date->toDateString();
            $inventory = $inventories->get($dateString);

            $calendar[] = [
                'date' => $dateString,
                'day_of_week' => $date->dayOfWeek,
                'total_rooms' => $inventory ? $inventory->total_rooms : $this->getDefaultRoomCount($ratePlanId),
                'available_rooms' => $inventory ? $inventory->available_rooms : $this->getDefaultRoomCount($ratePlanId),
                'reserved_rooms' => $inventory ? $inventory->reserved_rooms : 0,
                'booked_rooms' => $inventory ? $inventory->booked_rooms : 0,
                'is_available' => $inventory ? $inventory->available_rooms > 0 : true,
                'occupancy_rate' => $inventory ? $inventory->occupancy_rate : 0,
            ];
        }

        return collect($calendar);
    }

    /**
     * Get occupancy statistics for a period
     */
    public function getOccupancyStats($ratePlanId, $startDate, $endDate)
    {
        $inventories = Inventory::where('rate_plan_id', $ratePlanId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        if ($inventories->isEmpty()) {
            return [
                'total_room_nights' => 0,
                'booked_room_nights' => 0,
                'reserved_room_nights' => 0,
                'available_room_nights' => 0,
                'occupancy_rate' => 0,
                'average_daily_rate' => 0,
                'revenue_per_available_room' => 0,
            ];
        }

        $totalRoomNights = $inventories->sum('total_rooms');
        $bookedRoomNights = $inventories->sum('booked_rooms');
        $reservedRoomNights = $inventories->sum('reserved_rooms');
        $availableRoomNights = $inventories->sum('available_rooms');

        return [
            'total_room_nights' => $totalRoomNights,
            'booked_room_nights' => $bookedRoomNights,
            'reserved_room_nights' => $reservedRoomNights,
            'available_room_nights' => $availableRoomNights,
            'occupancy_rate' => $totalRoomNights > 0 ? round(($bookedRoomNights / $totalRoomNights) * 100, 2) : 0,
            'average_daily_rate' => $this->calculateAverageDailyRate($ratePlanId, $startDate, $endDate),
            'revenue_per_available_room' => $this->calculateRevPAR($ratePlanId, $startDate, $endDate),
        ];
    }

    /**
     * Calculate Average Daily Rate (ADR)
     */
    protected function calculateAverageDailyRate($ratePlanId, $startDate, $endDate)
    {
        $rates = SeasonalRate::where('rate_plan_id', $ratePlanId)
            ->whereBetween('valid_from', [$startDate, $endDate])
            ->orWhereBetween('valid_to', [$startDate, $endDate])
            ->get();

        return $rates->avg('nightly_price') ?? 0;
    }

    /**
     * Calculate Revenue Per Available Room (RevPAR)
     */
    protected function calculateRevPAR($ratePlanId, $startDate, $endDate)
    {
        $stats = $this->getOccupancyStats($ratePlanId, $startDate, $endDate);
        $adr = $this->calculateAverageDailyRate($ratePlanId, $startDate, $endDate);

        return ($stats['occupancy_rate'] / 100) * $adr;
    }

    /**
     * Get default room count for a rate plan
     */
    protected function getDefaultRoomCount($ratePlanId)
    {
        $ratePlan = RatePlan::find($ratePlanId);
        return $ratePlan ? $ratePlan->total_rooms : 10; // Default fallback
    }

    /**
     * Clean up expired reservations
     */
    public function cleanupExpiredReservations($olderThanMinutes = 30)
    {
        $cutoff = now()->subMinutes($olderThanMinutes);
        
        // Find bookings that are still pending but created more than X minutes ago
        $expiredBookings = Booking::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        $cleaned = 0;
        foreach ($expiredBookings as $booking) {
            $this->releaseReservation($booking->id);
            $booking->update(['status' => 'expired']);
            $cleaned++;
        }

        return $cleaned;
    }

    /**
     * Get low inventory alerts
     */
    public function getLowInventoryAlerts($threshold = 3, $daysAhead = 30)
    {
        $startDate = now()->toDateString();
        $endDate = now()->addDays($daysAhead)->toDateString();

        return Inventory::whereBetween('date', [$startDate, $endDate])
            ->where('available_rooms', '<=', $threshold)
            ->where('available_rooms', '>', 0)
            ->with(['ratePlan.roomType.resort'])
            ->orderBy('date')
            ->get()
            ->map(function ($inventory) {
                return [
                    'date' => $inventory->date,
                    'available_rooms' => $inventory->available_rooms,
                    'rate_plan' => $inventory->ratePlan->name,
                    'room_type' => $inventory->ratePlan->roomType->name,
                    'resort' => $inventory->ratePlan->roomType->resort->name,
                ];
            });
    }
}
