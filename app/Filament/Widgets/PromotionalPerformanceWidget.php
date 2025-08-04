<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Promotion;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PromotionalPerformanceWidget extends ChartWidget
{
    protected static ?string $heading = 'Top Performing Promotions';
    
    protected static ?int $sort = 6;
    
    protected function getData(): array
    {
        // Get promotion usage data for current month
        $promotionData = Booking::select([
                'promotions.code',
                'promotions.description',
                DB::raw('COUNT(bookings.id) as usage_count'),
                DB::raw('SUM(bookings.total_price_usd) as total_revenue'),
                DB::raw('AVG(bookings.total_price_usd) as avg_booking_value')
            ])
            ->join('promotions', 'bookings.promotion_id', '=', 'promotions.id')
            ->where('bookings.status', 'confirmed')
            ->whereMonth('bookings.created_at', now()->month)
            ->whereYear('bookings.created_at', now()->year)
            ->groupBy('promotions.id', 'promotions.code', 'promotions.description')
            ->orderByDesc('usage_count')
            ->limit(8)
            ->get();
        
        $labels = $promotionData->pluck('code')->toArray();
        $usageData = $promotionData->pluck('usage_count')->toArray();
        $revenueData = $promotionData->pluck('total_revenue')->toArray();
        
        return [
            'datasets' => [
                [
                    'label' => 'Usage Count',
                    'data' => $usageData,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                    'borderColor' => '#36A2EB',
                    'borderWidth' => 2,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Revenue (USD)',
                    'data' => $revenueData,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.6)',
                    'borderColor' => '#FF6384',
                    'borderWidth' => 2,
                    'yAxisID' => 'y1',
                    'type' => 'line',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Promotion Performance - Current Month',
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Usage Count'
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (USD)'
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Promotion Codes',
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
