<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class BookingTrendsWidget extends ChartWidget
{
    protected static ?string $heading = 'Booking Trends (Last 30 Days)';
    
    protected static ?int $sort = 3;
    
    // Full width for larger chart - non-static to match parent
    protected string | int | array $columnSpan = 'full';
    
    // Set a consistent height for charts
    protected static ?int $contentHeight = 300;
    
    public ?string $filter = '30';
    
    protected function getData(): array
    {
        $days = (int) $this->filter;
        
        // Get booking data for the specified period
        $bookings = collect();
        $revenues = collect();
        $labels = collect();
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateString = $date->format('M j');
            
            $dailyBookings = Booking::whereDate('created_at', $date->toDateString())->count();
            $dailyRevenue = Booking::where('status', 'confirmed')
                ->whereDate('created_at', $date->toDateString())
                ->sum('total_price_usd');
                
            $labels->push($dateString);
            $bookings->push($dailyBookings);
            $revenues->push((float) $dailyRevenue);
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $bookings->toArray(),
                    'borderColor' => '#36A2EB',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Revenue (USD)',
                    'data' => $revenues->toArray(),
                    'borderColor' => '#FF6384',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 3 months',
        ];
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Bookings'
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (USD)'
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
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
