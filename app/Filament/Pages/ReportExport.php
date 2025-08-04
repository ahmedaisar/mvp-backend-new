<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Resort;
use App\Models\Commission;
use App\Models\Promotion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportExport extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    
    protected static ?string $navigationLabel = 'Export Reports';
    
    protected static ?string $navigationGroup = 'Financial Reports';
    
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.report-export';
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->startOfMonth(),
            'date_to' => now()->endOfMonth(),
            'report_type' => 'bookings',
            'resorts' => [],
        ]);
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('report_type')
                    ->required()
                    ->options([
                        'bookings' => 'Booking Report',
                        'revenue' => 'Revenue Report',
                        'occupancy' => 'Occupancy Report',
                        'commissions' => 'Commission Report',
                        'promotions' => 'Promotion Usage Report',
                        'analytics' => 'Complete Analytics Report',
                    ])
                    ->default('bookings')
                    ->label('Report Type'),
                    
                DatePicker::make('date_from')
                    ->required()
                    ->default(now()->startOfMonth())
                    ->label('From Date'),
                    
                DatePicker::make('date_to')
                    ->required()
                    ->default(now()->endOfMonth())
                    ->after('date_from')
                    ->label('To Date'),
                    
                CheckboxList::make('resorts')
                    ->options(Resort::pluck('name', 'id'))
                    ->columns(2)
                    ->label('Resorts (Leave empty for all)'),
                    
                Select::make('format')
                    ->required()
                    ->options([
                        'csv' => 'CSV',
                        'excel' => 'Excel',
                        'pdf' => 'PDF',
                    ])
                    ->default('csv')
                    ->label('Export Format'),
            ])
            ->statePath('data');
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Report')
                ->color('primary')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportReport'),
                
            Action::make('preview')
                ->label('Preview Data')
                ->color('gray')
                ->icon('heroicon-o-eye')
                ->action('previewReport'),
        ];
    }
    
    public function exportReport(): void
    {
        $data = $this->form->getState();
        
        try {
            $reportData = $this->generateReportData($data);
            $filename = $this->generateFilename($data);
            
            switch ($data['format']) {
                case 'csv':
                    $this->exportToCsv($reportData, $filename);
                    break;
                case 'excel':
                    $this->exportToExcel($reportData, $filename);
                    break;
                case 'pdf':
                    $this->exportToPdf($reportData, $filename);
                    break;
            }
            
            Notification::make()
                ->title('Report exported successfully')
                ->body("Report saved as {$filename}")
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function previewReport(): void
    {
        $data = $this->form->getState();
        $reportData = $this->generateReportData($data);
        
        // Store preview data in session for display
        session(['report_preview' => array_slice($reportData, 0, 10)]);
        
        Notification::make()
            ->title('Preview generated')
            ->body('Showing first 10 rows of report data')
            ->success()
            ->send();
    }
    
    protected function generateReportData(array $params): array
    {
        $dateFrom = Carbon::parse($params['date_from']);
        $dateTo = Carbon::parse($params['date_to']);
        $resorts = $params['resorts'] ?? [];
        
        switch ($params['report_type']) {
            case 'bookings':
                return $this->generateBookingsReport($dateFrom, $dateTo, $resorts);
            case 'revenue':
                return $this->generateRevenueReport($dateFrom, $dateTo, $resorts);
            case 'occupancy':
                return $this->generateOccupancyReport($dateFrom, $dateTo, $resorts);
            case 'commissions':
                return $this->generateCommissionsReport($dateFrom, $dateTo, $resorts);
            case 'promotions':
                return $this->generatePromotionsReport($dateFrom, $dateTo, $resorts);
            case 'analytics':
                return $this->generateAnalyticsReport($dateFrom, $dateTo, $resorts);
            default:
                return [];
        }
    }
    
    protected function generateBookingsReport(Carbon $dateFrom, Carbon $dateTo, array $resorts): array
    {
        $query = Booking::with(['resort', 'roomType', 'guestProfile', 'promotion', 'commission'])
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
            
        if (!empty($resorts)) {
            $query->whereIn('resort_id', $resorts);
        }
        
        $bookings = $query->get();
        
        $data = [['Booking Reference', 'Resort', 'Room Type', 'Guest Name', 'Check In', 'Check Out', 'Status', 'Total Price', 'Commission', 'Promotion']];
        
        foreach ($bookings as $booking) {
            $data[] = [
                $booking->booking_reference,
                $booking->resort->name ?? 'N/A',
                $booking->roomType->name ?? 'N/A',
                $booking->guestProfile->full_name ?? 'N/A',
                $booking->check_in->format('Y-m-d'),
                $booking->check_out->format('Y-m-d'),
                ucfirst($booking->status),
                '$' . number_format($booking->total_price_usd, 2),
                $booking->commission->name ?? 'None',
                $booking->promotion->code ?? 'None',
            ];
        }
        
        return $data;
    }
    
    protected function generateRevenueReport(Carbon $dateFrom, Carbon $dateTo, array $resorts): array
    {
        $query = Booking::selectRaw('
                resorts.name as resort_name,
                DATE(bookings.created_at) as booking_date,
                COUNT(bookings.id) as booking_count,
                SUM(bookings.total_price_usd) as total_revenue,
                AVG(bookings.total_price_usd) as avg_booking_value
            ')
            ->join('resorts', 'bookings.resort_id', '=', 'resorts.id')
            ->where('bookings.status', 'confirmed')
            ->whereBetween('bookings.created_at', [$dateFrom, $dateTo])
            ->groupBy('resorts.id', 'resorts.name', 'booking_date')
            ->orderBy('booking_date');
            
        if (!empty($resorts)) {
            $query->whereIn('bookings.resort_id', $resorts);
        }
        
        $revenue = $query->get();
        
        $data = [['Date', 'Resort', 'Bookings', 'Total Revenue (USD)', 'Avg Booking Value (USD)']];
        
        foreach ($revenue as $row) {
            $data[] = [
                $row->booking_date,
                $row->resort_name,
                $row->booking_count,
                number_format($row->total_revenue, 2),
                number_format($row->avg_booking_value, 2),
            ];
        }
        
        return $data;
    }
    
    protected function generateCommissionsReport(Carbon $dateFrom, Carbon $dateTo, array $resorts): array
    {
        $query = Booking::with(['commission', 'resort'])
            ->whereHas('commission')
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
            
        if (!empty($resorts)) {
            $query->whereIn('resort_id', $resorts);
        }
        
        $bookings = $query->get();
        
        $data = [['Agent Code', 'Agent Name', 'Resort', 'Booking Reference', 'Booking Value', 'Commission Rate', 'Commission Amount']];
        
        foreach ($bookings as $booking) {
            $nights = $booking->check_in->diffInDays($booking->check_out);
            $commission = $booking->commission->calculateCommission($booking->total_price_usd, $nights);
            
            $data[] = [
                $booking->commission->agent_code,
                $booking->commission->name,
                $booking->resort->name,
                $booking->booking_reference,
                '$' . number_format($booking->total_price_usd, 2),
                $booking->commission->commission_rate . '%',
                '$' . number_format($commission, 2),
            ];
        }
        
        return $data;
    }
    
    protected function generatePromotionsReport(Carbon $dateFrom, Carbon $dateTo, array $resorts): array
    {
        $query = Booking::with(['promotion', 'resort'])
            ->whereHas('promotion')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
            
        if (!empty($resorts)) {
            $query->whereIn('resort_id', $resorts);
        }
        
        $bookings = $query->get()->groupBy('promotion.code');
        
        $data = [['Promotion Code', 'Usage Count', 'Total Revenue', 'Average Booking Value', 'Total Discount']];
        
        foreach ($bookings as $promoCode => $promoBookings) {
            $totalRevenue = $promoBookings->sum('total_price_usd');
            $totalDiscount = $promoBookings->sum('discount_amount');
            $avgBookingValue = $promoBookings->avg('total_price_usd');
            
            $data[] = [
                $promoCode,
                $promoBookings->count(),
                '$' . number_format($totalRevenue, 2),
                '$' . number_format($avgBookingValue, 2),
                '$' . number_format($totalDiscount, 2),
            ];
        }
        
        return $data;
    }
    
    protected function generateAnalyticsReport(Carbon $dateFrom, Carbon $dateTo, array $resorts): array
    {
        // This would be a comprehensive report combining multiple metrics
        $data = [['Metric', 'Value', 'Period']];
        
        $query = Booking::whereBetween('created_at', [$dateFrom, $dateTo]);
        if (!empty($resorts)) {
            $query->whereIn('resort_id', $resorts);
        }
        
        $totalBookings = $query->count();
        $confirmedBookings = $query->where('status', 'confirmed')->count();
        $totalRevenue = $query->where('status', 'confirmed')->sum('total_price_usd');
        $avgBookingValue = $query->where('status', 'confirmed')->avg('total_price_usd');
        
        $data[] = ['Total Bookings', $totalBookings, $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')];
        $data[] = ['Confirmed Bookings', $confirmedBookings, $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')];
        $data[] = ['Total Revenue (USD)', number_format($totalRevenue, 2), $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')];
        $data[] = ['Average Booking Value (USD)', number_format($avgBookingValue, 2), $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')];
        
        return $data;
    }
    
    protected function generateFilename(array $params): string
    {
        $reportType = $params['report_type'];
        $dateFrom = Carbon::parse($params['date_from'])->format('Y-m-d');
        $dateTo = Carbon::parse($params['date_to'])->format('Y-m-d');
        $format = $params['format'];
        
        return "{$reportType}_report_{$dateFrom}_to_{$dateTo}.{$format}";
    }
    
    protected function exportToCsv(array $data, string $filename): void
    {
        $output = fopen('php://temp', 'w');
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        Storage::put("reports/{$filename}", $csvContent);
    }
    
    protected function exportToExcel(array $data, string $filename): void
    {
        // For now, export as CSV with .xlsx extension
        $this->exportToCsv($data, $filename);
    }
    
    protected function exportToPdf(array $data, string $filename): void
    {
        // For now, export as CSV with .pdf extension
        $this->exportToCsv($data, $filename);
    }
}
