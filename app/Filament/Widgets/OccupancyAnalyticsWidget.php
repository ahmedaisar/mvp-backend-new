<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Resort;
use App\Models\RoomType;
use App\Models\Inventory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class OccupancyAnalyticsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    public ?string $filter = '30';
    
    protected function getStats(): array
    {
        $days = (int) $this->filter;
        $startDate = now()->subDays($days);
        $endDate = now();
        
        // Calculate Occupancy Rate
        $occupancyRate = $this->calculateOccupancyRate($startDate, $endDate);
        
        // Calculate ADR (Average Daily Rate)
        $adr = $this->calculateADR($startDate, $endDate);
        
        // Calculate RevPAR (Revenue Per Available Room)
        $revpar = $this->calculateRevPAR($startDate, $endDate);
        
        // Calculate booking conversion rate
        $conversionRate = $this->calculateConversionRate($startDate, $endDate);
        
        return [
            Stat::make('Occupancy Rate', number_format($occupancyRate, 1) . '%')
                ->description($this->getOccupancyTrend($startDate, $endDate))
                ->descriptionIcon($occupancyRate >= 70 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($occupancyRate >= 70 ? 'success' : ($occupancyRate >= 50 ? 'warning' : 'danger'))
                ->chart($this->getOccupancyChart($startDate, $endDate)),
                
            Stat::make('ADR', '$' . number_format($adr, 2))
                ->description('Average Daily Rate')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info')
                ->chart($this->getADRChart($startDate, $endDate)),
                
            Stat::make('RevPAR', '$' . number_format($revpar, 2))
                ->description('Revenue Per Available Room')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning')
                ->chart($this->getRevPARChart($startDate, $endDate)),
                
            Stat::make('Conversion Rate', number_format($conversionRate, 1) . '%')
                ->description('Booking to inquiry ratio')
                ->descriptionIcon('heroicon-m-funnel')
                ->color($conversionRate >= 15 ? 'success' : 'warning'),
        ];
    }
    
    protected function calculateOccupancyRate(Carbon $startDate, Carbon $endDate): float
    {
        $totalRoomNights = 0;
        $occupiedRoomNights = 0;
        
        // Get all confirmed bookings in period
        $bookings = Booking::where('status', 'confirmed')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('check_in', [$startDate, $endDate])
                      ->orWhereBetween('check_out', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('check_in', '<=', $startDate)
                            ->where('check_out', '>=', $endDate);
                      });
            })
            ->with(['roomType.resort'])
            ->get();
        
        // Calculate occupied room nights
        foreach ($bookings as $booking) {
            $checkIn = max($booking->check_in, $startDate);
            $checkOut = min($booking->check_out, $endDate);
            $nights = $checkIn->diffInDays($checkOut);
            $occupiedRoomNights += $nights;
        }
        
        // Calculate total available room nights
        $resorts = Resort::with(['roomTypes.ratePlans'])->get();
        $period = CarbonPeriod::create($startDate, $endDate->subDay());
        
        foreach ($resorts as $resort) {
            foreach ($resort->roomTypes as $roomType) {
                foreach ($period as $date) {
                    // Find rate plans for the room type
                    $ratePlanIds = $roomType->ratePlans->pluck('id')->toArray();
                    
                    // Find inventory for any rate plan of this room type for the given date
                    $inventory = Inventory::whereIn('rate_plan_id', $ratePlanIds)
                        ->where('start_date', '<=', $date->toDateString())
                        ->where('end_date', '>=', $date->toDateString())
                        ->first();
                    
                    if ($inventory && !$inventory->blocked) {
                        $totalRoomNights += $inventory->available_rooms ?? 1;
                    }
                }
            }
        }
        
        return $totalRoomNights > 0 ? ($occupiedRoomNights / $totalRoomNights) * 100 : 0;
    }
    
    protected function calculateADR(Carbon $startDate, Carbon $endDate): float
    {
        $bookings = Booking::where('status', 'confirmed')
            ->whereBetween('check_in', [$startDate, $endDate])
            ->get();
        
        if ($bookings->isEmpty()) {
            return 0;
        }
        
        $totalRevenue = $bookings->sum('total_price_usd');
        $totalRoomNights = $bookings->sum(function ($booking) {
            return $booking->check_in->diffInDays($booking->check_out);
        });
        
        return $totalRoomNights > 0 ? $totalRevenue / $totalRoomNights : 0;
    }
    
    protected function calculateRevPAR(Carbon $startDate, Carbon $endDate): float
    {
        $occupancyRate = $this->calculateOccupancyRate($startDate, $endDate) / 100;
        $adr = $this->calculateADR($startDate, $endDate);
        
        return $occupancyRate * $adr;
    }
    
    protected function calculateConversionRate(Carbon $startDate, Carbon $endDate): float
    {
        // This would typically require tracking inquiries/searches
        // For now, let's use a simplified calculation based on bookings vs total interactions
        $confirmedBookings = Booking::where('status', 'confirmed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        
        $totalBookings = Booking::whereBetween('created_at', [$startDate, $endDate])
            ->count();
        
        return $totalBookings > 0 ? ($confirmedBookings / $totalBookings) * 100 : 0;
    }
    
    protected function getOccupancyTrend(Carbon $startDate, Carbon $endDate): string
    {
        $previousPeriod = $startDate->copy()->subDays($startDate->diffInDays($endDate));
        $previousOccupancy = $this->calculateOccupancyRate($previousPeriod, $startDate);
        $currentOccupancy = $this->calculateOccupancyRate($startDate, $endDate);
        
        $trend = $currentOccupancy - $previousOccupancy;
        
        if ($trend > 0) {
            return '+' . number_format($trend, 1) . '% from previous period';
        } elseif ($trend < 0) {
            return number_format($trend, 1) . '% from previous period';
        } else {
            return 'No change from previous period';
        }
    }
    
    protected function getOccupancyChart(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $period = CarbonPeriod::create($startDate, $endDate->subDay());
        
        foreach ($period as $date) {
            $dailyOccupancy = $this->calculateOccupancyRate($date, $date->copy()->addDay());
            $data[] = $dailyOccupancy;
        }
        
        return $data;
    }
    
    protected function getADRChart(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $period = CarbonPeriod::create($startDate, $endDate->subDay());
        
        foreach ($period as $date) {
            $dailyADR = $this->calculateADR($date, $date->copy()->addDay());
            $data[] = (float) $dailyADR;
        }
        
        return $data;
    }
    
    protected function getRevPARChart(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $period = CarbonPeriod::create($startDate, $endDate->subDay());
        
        foreach ($period as $date) {
            $dailyRevPAR = $this->calculateRevPAR($date, $date->copy()->addDay());
            $data[] = (float) $dailyRevPAR;
        }
        
        return $data;
    }
    
    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 3 months',
        ];
    }
}
