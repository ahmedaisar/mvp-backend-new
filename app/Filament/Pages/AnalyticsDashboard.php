<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OccupancyAnalyticsWidget;
use App\Filament\Widgets\ResortPerformanceWidget;
use App\Filament\Widgets\BookingTrendsWidget;
use App\Filament\Widgets\CancellationAnalyticsWidget;
use App\Filament\Widgets\PromotionalPerformanceWidget;
use App\Filament\Widgets\CommissionAnalyticsWidget;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;

class AnalyticsDashboard extends Page
{
    use HasFiltersForm;
    
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    
    protected static ?string $navigationLabel = 'Analytics';
    
    protected static ?string $navigationGroup = 'Financial Reports';
    
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.analytics-dashboard';
    
    protected function getHeaderWidgets(): array
    {
        return [
            OccupancyAnalyticsWidget::class,
        ];
    }
    
    protected function getFooterWidgets(): array
    {
        return [
            BookingTrendsWidget::class,
            ResortPerformanceWidget::class,
            CancellationAnalyticsWidget::class,
            PromotionalPerformanceWidget::class,
            CommissionAnalyticsWidget::class,
        ];
    }
}
