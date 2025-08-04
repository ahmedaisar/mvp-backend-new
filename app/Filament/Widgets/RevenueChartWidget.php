<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Resort;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue by Resort';
    
    protected static ?int $sort = 2;
    
    protected function getData(): array
    {
        // Get revenue by resort for current month
        $revenues = Booking::selectRaw('resorts.name, SUM(bookings.total_price_usd) as revenue')
            ->join('room_types', 'bookings.room_type_id', '=', 'room_types.id')
            ->join('resorts', 'room_types.resort_id', '=', 'resorts.id')
            ->where('bookings.status', 'confirmed')
            ->whereMonth('bookings.created_at', now()->month)
            ->whereYear('bookings.created_at', now()->year)
            ->groupBy('resorts.id', 'resorts.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();
            
        return [
            'datasets' => [
                [
                    'label' => 'Revenue (USD)',
                    'data' => $revenues->pluck('revenue')->toArray(),
                    'backgroundColor' => [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#FF6384',
                        '#C9CBCF'
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $revenues->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
