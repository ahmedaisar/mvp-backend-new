<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Resort;
use App\Models\User;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class BookingStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get current month and previous month data
        $currentMonth = now()->startOfMonth();
        $previousMonth = now()->subMonth()->startOfMonth();
        
        // Current month bookings
        $currentMonthBookings = Booking::whereBetween('created_at', [
            $currentMonth,
            $currentMonth->copy()->endOfMonth()
        ])->count();
        
        // Previous month bookings
        $previousMonthBookings = Booking::whereBetween('created_at', [
            $previousMonth,
            $previousMonth->copy()->endOfMonth()
        ])->count();
        
        // Calculate booking trend
        $bookingTrend = $previousMonthBookings > 0 
            ? (($currentMonthBookings - $previousMonthBookings) / $previousMonthBookings) * 100
            : 0;
            
        // Current month revenue
        $currentMonthRevenue = Booking::where('status', 'confirmed')
            ->whereBetween('created_at', [
                $currentMonth,
                $currentMonth->copy()->endOfMonth()
            ])->sum('total_price_usd');
            
        // Previous month revenue
        $previousMonthRevenue = Booking::where('status', 'confirmed')
            ->whereBetween('created_at', [
                $previousMonth,
                $previousMonth->copy()->endOfMonth()
            ])->sum('total_price_usd');
            
        // Calculate revenue trend
        $revenueTrend = $previousMonthRevenue > 0 
            ? (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100
            : 0;
            
        // Active resorts
        $activeResorts = Resort::where('active', true)->count();
        $totalResorts = Resort::count();
        
        // Recent registrations (last 30 days)
        $recentUsers = User::where('created_at', '>=', now()->subDays(30))->count();
        
        return [
            Stat::make('Total Bookings', Booking::count())
                ->description($currentMonthBookings . ' this month')
                ->descriptionIcon($bookingTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($bookingTrend >= 0 ? 'success' : 'danger')
                ->chart($this->getBookingChart()),
                
            Stat::make('Monthly Revenue', '$' . number_format($currentMonthRevenue, 2))
                ->description(($revenueTrend >= 0 ? '+' : '') . number_format($revenueTrend, 1) . '% from last month')
                ->descriptionIcon($revenueTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueTrend >= 0 ? 'success' : 'danger')
                ->chart($this->getRevenueChart()),
                
            Stat::make('Active Resorts', $activeResorts)
                ->description($totalResorts . ' total resorts')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('info'),
                
            Stat::make('New Users', $recentUsers)
                ->description('Last 30 days')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
    
    protected function getBookingChart(): array
    {
        // Get last 7 days booking counts
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $count = Booking::whereDate('created_at', $date)->count();
            $data[] = $count;
        }
        return $data;
    }
    
    protected function getRevenueChart(): array
    {
        // Get last 7 days revenue
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $revenue = Booking::where('status', 'confirmed')
                ->whereDate('created_at', $date)
                ->sum('total_price_usd');
            $data[] = (float) $revenue;
        }
        return $data;
    }
}
