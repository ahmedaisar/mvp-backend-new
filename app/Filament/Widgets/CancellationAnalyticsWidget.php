<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Resort;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class CancellationAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Booking Status Distribution';
    
    protected static ?int $sort = 5;
    
    public ?string $filter = '30';
    
    protected function getData(): array
    {
        $days = (int) $this->filter;
        $startDate = now()->subDays($days);
        
        $statusCounts = Booking::selectRaw('status, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
        
        // Calculate cancellation rate
        $totalBookings = array_sum($statusCounts);
        $cancelledBookings = $statusCounts['cancelled'] ?? 0;
        $cancellationRate = $totalBookings > 0 ? ($cancelledBookings / $totalBookings) * 100 : 0;
        
        $labels = [];
        $data = [];
        $colors = [];
        
        $statusColors = [
            'pending' => '#FFA500',
            'confirmed' => '#28A745',
            'cancelled' => '#DC3545',
            'completed' => '#17A2B8',
            'no_show' => '#6C757D',
        ];
        
        $statusLabels = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            'no_show' => 'No Show',
        ];
        
        foreach ($statusCounts as $status => $count) {
            $labels[] = $statusLabels[$status] ?? ucfirst($status);
            $data[] = $count;
            $colors[] = $statusColors[$status] ?? '#6C757D';
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
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
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            var label = context.label || "";
                            var value = context.parsed;
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var percentage = ((value / total) * 100).toFixed(1);
                            return label + ": " + value + " (" + percentage + "%)";
                        }'
                    ]
                ]
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
