<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Commission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class CommissionAnalyticsWidget extends BaseWidget
{
    protected static ?int $sort = 7;
    
    protected function getStats(): array
    {
        $currentMonth = now()->startOfMonth();
        $previousMonth = now()->subMonth()->startOfMonth();
        
        // Current month commission calculations
        $currentCommissions = $this->calculateTotalCommissions($currentMonth, $currentMonth->copy()->endOfMonth());
        $previousCommissions = $this->calculateTotalCommissions($previousMonth, $previousMonth->copy()->endOfMonth());
        
        // Commission trend
        $commissionTrend = $previousCommissions > 0 
            ? (($currentCommissions - $previousCommissions) / $previousCommissions) * 100
            : 0;
        
        // Active commission partners
        $activePartners = Commission::active()->count();
        
        // Top performing agent this month
        $topAgent = $this->getTopPerformingAgent($currentMonth, $currentMonth->copy()->endOfMonth());
        
        // Average commission rate
        $avgCommissionRate = Commission::active()
            ->where('commission_type', 'percentage')
            ->avg('commission_rate') ?? 0;
        
        return [
            Stat::make('Monthly Commissions', '$' . number_format($currentCommissions, 2))
                ->description(($commissionTrend >= 0 ? '+' : '') . number_format($commissionTrend, 1) . '% from last month')
                ->descriptionIcon($commissionTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($commissionTrend >= 0 ? 'success' : 'danger')
                ->chart($this->getCommissionChart()),
                
            Stat::make('Active Partners', $activePartners)
                ->description('Commission partners')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
                
            Stat::make('Top Agent', $topAgent['name'] ?? 'N/A')
                ->description('$' . number_format($topAgent['commission'] ?? 0, 2) . ' this month')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning'),
                
            Stat::make('Avg. Commission Rate', number_format($avgCommissionRate, 1) . '%')
                ->description('Average percentage rate')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('success'),
        ];
    }
    
    protected function calculateTotalCommissions(Carbon $startDate, Carbon $endDate): float
    {
        $totalCommissions = 0;
        
        // Get all confirmed bookings in the period
        $bookings = Booking::with(['commission'])
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
        
        foreach ($bookings as $booking) {
            if ($booking->commission) {
                $nights = $booking->check_in->diffInDays($booking->check_out);
                $commission = $booking->commission->calculateCommission(
                    $booking->total_price_usd, 
                    $nights
                );
                $totalCommissions += $commission;
            }
        }
        
        return $totalCommissions;
    }
    
    protected function getTopPerformingAgent(Carbon $startDate, Carbon $endDate): array
    {
        $agentPerformance = [];
        
        $bookings = Booking::with(['commission'])
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
        
        foreach ($bookings as $booking) {
            if ($booking->commission) {
                $agentCode = $booking->commission->agent_code;
                $nights = $booking->check_in->diffInDays($booking->check_out);
                $commission = $booking->commission->calculateCommission(
                    $booking->total_price_usd, 
                    $nights
                );
                
                if (!isset($agentPerformance[$agentCode])) {
                    $agentPerformance[$agentCode] = [
                        'name' => $booking->commission->name,
                        'commission' => 0,
                        'bookings' => 0,
                    ];
                }
                
                $agentPerformance[$agentCode]['commission'] += $commission;
                $agentPerformance[$agentCode]['bookings']++;
            }
        }
        
        if (empty($agentPerformance)) {
            return ['name' => 'N/A', 'commission' => 0];
        }
        
        // Sort by commission and return top performer
        uasort($agentPerformance, function ($a, $b) {
            return $b['commission'] <=> $a['commission'];
        });
        
        return array_values($agentPerformance)[0];
    }
    
    protected function getCommissionChart(): array
    {
        // Get last 7 days commission data
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayCommissions = $this->calculateTotalCommissions($date, $date->copy()->endOfDay());
            $data[] = (float) $dayCommissions;
        }
        return $data;
    }
}
