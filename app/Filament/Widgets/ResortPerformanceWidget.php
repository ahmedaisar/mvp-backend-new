<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Resort;
use Filament\Widgets\ChartWidget;

class ResortPerformanceWidget extends ChartWidget
{
    protected static ?string $heading = 'Resort Performance - Revenue Comparison';
    
    protected static ?int $sort = 5;
    
    // Full width for bar chart
    protected string | int | array $columnSpan = 'full';
    
    // Set a consistent height for bar charts
    protected static ?int $contentHeight = 300;
    
    protected function getData(): array
    {
        // Get revenue by resort for current month
        $revenueData = Booking::selectRaw('resorts.name, SUM(bookings.total_price_usd) as revenue')
            ->join('resorts', 'bookings.resort_id', '=', 'resorts.id')
            ->where('bookings.status', 'confirmed')
            ->whereMonth('bookings.created_at', now()->month)
            ->whereYear('bookings.created_at', now()->year)
            ->groupBy('resorts.id', 'resorts.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
        
        return [
            'datasets' => [
                [
                    'label' => 'Revenue (USD)',
                    'data' => $revenueData->pluck('revenue')->toArray(),
                    'backgroundColor' => [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                        '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF9F40'
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $revenueData->pluck('name')->toArray(),
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
                    'display' => false,
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Resort Revenue Comparison - Current Month',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (USD)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Resorts',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
