<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Transaction;
use App\Models\GuestProfile;
use App\Models\Resort;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get comprehensive dashboard overview data
     */
    public function overview(Request $request)
    {
        $period = $request->get('period', '30'); // days
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();

        $data = [
            'summary' => $this->getSummaryStats($startDate, $endDate),
            'revenue' => $this->getRevenueData($startDate, $endDate),
            'bookings' => $this->getBookingData($startDate, $endDate),
            'occupancy' => $this->getOccupancyData($startDate, $endDate),
            'guests' => $this->getGuestData($startDate, $endDate),
            'recent_activity' => $this->getRecentActivity(),
            'alerts' => $this->getSystemAlerts(),
            'performance' => $this->getPerformanceMetrics($startDate, $endDate),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'period' => $period,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get summary statistics
     */
    protected function getSummaryStats($startDate, $endDate)
    {
        $previousPeriod = $startDate->copy()->subDays($endDate->diffInDays($startDate));
        
        $current = [
            'total_bookings' => Booking::whereBetween('created_at', [$startDate, $endDate])->count(),
            'total_revenue' => Transaction::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount_usd'),
            'total_guests' => GuestProfile::whereBetween('created_at', [$startDate, $endDate])->count(),
            'average_booking_value' => Booking::whereBetween('created_at', [$startDate, $endDate])
                ->avg('total_price_usd'),
        ];

        $previous = [
            'total_bookings' => Booking::whereBetween('created_at', [$previousPeriod, $startDate])->count(),
            'total_revenue' => Transaction::where('status', 'completed')
                ->whereBetween('created_at', [$previousPeriod, $startDate])
                ->sum('amount_usd'),
            'total_guests' => GuestProfile::whereBetween('created_at', [$previousPeriod, $startDate])->count(),
            'average_booking_value' => Booking::whereBetween('created_at', [$previousPeriod, $startDate])
                ->avg('total_price_usd'),
        ];

        return [
            'current' => $current,
            'previous' => $previous,
            'growth' => [
                'bookings' => $this->calculateGrowthRate($previous['total_bookings'], $current['total_bookings']),
                'revenue' => $this->calculateGrowthRate($previous['total_revenue'], $current['total_revenue']),
                'guests' => $this->calculateGrowthRate($previous['total_guests'], $current['total_guests']),
                'avg_booking_value' => $this->calculateGrowthRate($previous['average_booking_value'], $current['average_booking_value']),
            ],
        ];
    }

    /**
     * Get revenue data with breakdown
     */
    protected function getRevenueData($startDate, $endDate)
    {
        $dailyRevenue = Transaction::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(amount_usd) as revenue'),
            DB::raw('COUNT(*) as transactions')
        )
        ->where('status', 'completed')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        $revenueByResort = Transaction::select(
            'bookings.resort_id',
            'resorts.name as resort_name',
            DB::raw('SUM(transactions.amount_usd) as revenue'),
            DB::raw('COUNT(transactions.id) as transactions')
        )
        ->join('bookings', 'transactions.booking_id', '=', 'bookings.id')
        ->join('resorts', 'bookings.resort_id', '=', 'resorts.id')
        ->where('transactions.status', 'completed')
        ->whereBetween('transactions.created_at', [$startDate, $endDate])
        ->groupBy('bookings.resort_id', 'resorts.name')
        ->orderByDesc('revenue')
        ->get();

        $revenueByPaymentMethod = Transaction::select(
            'payment_method',
            DB::raw('SUM(amount_usd) as revenue'),
            DB::raw('COUNT(*) as transactions')
        )
        ->where('status', 'completed')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('payment_method')
        ->orderByDesc('revenue')
        ->get();

        return [
            'daily_revenue' => $dailyRevenue,
            'total_revenue' => $dailyRevenue->sum('revenue'),
            'by_resort' => $revenueByResort,
            'by_payment_method' => $revenueByPaymentMethod,
        ];
    }

    /**
     * Get booking data and trends
     */
    protected function getBookingData($startDate, $endDate)
    {
        $bookingTrends = Booking::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as bookings'),
            'status'
        )
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('date', 'status')
        ->orderBy('date')
        ->get();

        $bookingsByStatus = Booking::select(
            'status',
            DB::raw('COUNT(*) as count')
        )
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('status')
        ->get();

        $averageBookingValue = Booking::whereBetween('created_at', [$startDate, $endDate])
            ->avg('total_price_usd');

        $topDestinations = Booking::select(
            'resort_id',
            'resorts.name as resort_name',
            DB::raw('COUNT(*) as bookings'),
            DB::raw('AVG(total_price_usd) as avg_value')
        )
        ->join('resorts', 'bookings.resort_id', '=', 'resorts.id')
        ->whereBetween('bookings.created_at', [$startDate, $endDate])
        ->groupBy('resort_id', 'resorts.name')
        ->orderByDesc('bookings')
        ->limit(10)
        ->get();

        return [
            'trends' => $bookingTrends,
            'by_status' => $bookingsByStatus,
            'average_value' => $averageBookingValue,
            'top_destinations' => $topDestinations,
        ];
    }

    /**
     * Get occupancy data and forecasts
     */
    protected function getOccupancyData($startDate, $endDate)
    {
        $occupancyByResort = DB::table('bookings')
            ->select(
                'resort_id',
                'resorts.name as resort_name',
                DB::raw('COUNT(DISTINCT DATE(check_in)) as occupied_nights'),
                DB::raw('COUNT(*) as total_bookings')
            )
            ->join('resorts', 'bookings.resort_id', '=', 'resorts.id')
            ->where('bookings.status', 'confirmed')
            ->whereBetween('bookings.check_in', [$startDate, $endDate])
            ->groupBy('resort_id', 'resorts.name')
            ->get();

        $upcomingOccupancy = Booking::select(
            DB::raw('DATE(check_in) as date'),
            DB::raw('COUNT(*) as bookings'),
            'resort_id'
        )
        ->where('status', 'confirmed')
        ->whereBetween('check_in', [now(), now()->addDays(30)])
        ->groupBy('date', 'resort_id')
        ->orderBy('date')
        ->get();

        return [
            'by_resort' => $occupancyByResort,
            'upcoming' => $upcomingOccupancy,
        ];
    }

    /**
     * Get guest analytics data
     */
    protected function getGuestData($startDate, $endDate)
    {
        $newGuests = GuestProfile::whereBetween('created_at', [$startDate, $endDate])->count();
        
        $returningGuests = GuestProfile::whereHas('bookings', function($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })
        ->withCount(['bookings' => function($query) {
            $query->where('created_at', '<', now()->subDays(30));
        }])
        ->having('bookings_count', '>', 0)
        ->count();

        $guestsByCountry = GuestProfile::select(
            'country',
            DB::raw('COUNT(*) as count')
        )
        ->whereBetween('created_at', [$startDate, $endDate])
        ->whereNotNull('country')
        ->groupBy('country')
        ->orderByDesc('count')
        ->limit(10)
        ->get();

        return [
            'new_guests' => $newGuests,
            'returning_guests' => $returningGuests,
            'by_country' => $guestsByCountry,
            'retention_rate' => $newGuests > 0 ? round(($returningGuests / ($newGuests + $returningGuests)) * 100, 2) : 0,
        ];
    }

    /**
     * Get recent system activity
     */
    protected function getRecentActivity()
    {
        $recentBookings = Booking::with(['guestProfile', 'resort'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentTransactions = Transaction::with(['booking.guestProfile', 'booking.resort'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentAuditLogs = AuditLog::with('user')
            ->where('severity', 'high')
            ->orWhere('severity', 'critical')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'recent_bookings' => $recentBookings,
            'recent_transactions' => $recentTransactions,
            'security_alerts' => $recentAuditLogs,
        ];
    }

    /**
     * Get system alerts and warnings
     */
    protected function getSystemAlerts()
    {
        $alerts = [];

        // Check for pending transactions
        $pendingTransactions = Transaction::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        if ($pendingTransactions > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Pending Transactions',
                'message' => "{$pendingTransactions} transactions pending for over 24 hours",
                'action_url' => '/admin/transactions?status=pending',
            ];
        }

        // Check for failed payments
        $failedPayments = Transaction::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($failedPayments > 10) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'High Payment Failure Rate',
                'message' => "{$failedPayments} failed payments in the last 7 days",
                'action_url' => '/admin/transactions?status=failed',
            ];
        }

        // Check for bookings requiring attention
        $problemBookings = Booking::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(3))
            ->count();

        if ($problemBookings > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Bookings Requiring Attention',
                'message' => "{$problemBookings} bookings pending for over 3 days",
                'action_url' => '/admin/bookings?status=pending',
            ];
        }

        // Check for security events
        $securityEvents = AuditLog::where('event_type', 'security_event')
            ->where('created_at', '>=', now()->subDays(1))
            ->count();

        if ($securityEvents > 0) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Security Events',
                'message' => "{$securityEvents} security events in the last 24 hours",
                'action_url' => '/admin/audit-logs?event_type=security_event',
            ];
        }

        return $alerts;
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics($startDate, $endDate)
    {
        $totalPageViews = AuditLog::where('action', 'page_view')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $conversionRate = $this->calculateConversionRate($startDate, $endDate);
        
        $averageResponseTime = AuditLog::where('action', 'api_request')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('response_time') ?? 0;

        $errorRate = $this->calculateErrorRate($startDate, $endDate);

        return [
            'page_views' => $totalPageViews,
            'conversion_rate' => $conversionRate,
            'avg_response_time' => round($averageResponseTime, 2),
            'error_rate' => $errorRate,
        ];
    }

    /**
     * Get revenue report for specific period
     */
    public function revenueReport(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date', now()->subMonth()));
        $endDate = Carbon::parse($request->get('end_date', now()));
        $groupBy = $request->get('group_by', 'day'); // day, week, month

        $dateFormat = match($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $revenue = Transaction::select(
            DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
            DB::raw('SUM(amount_usd) as revenue'),
            DB::raw('COUNT(*) as transactions'),
            DB::raw('AVG(amount_usd) as avg_transaction')
        )
        ->where('status', 'completed')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('period')
        ->orderBy('period')
        ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => $revenue,
                'total_revenue' => $revenue->sum('revenue'),
                'total_transactions' => $revenue->sum('transactions'),
                'average_transaction' => $revenue->avg('avg_transaction'),
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'group_by' => $groupBy,
                ],
            ],
        ]);
    }

    /**
     * Get occupancy report
     */
    public function occupancyReport(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date', now()));
        $endDate = Carbon::parse($request->get('end_date', now()->addMonth()));
        $resortId = $request->get('resort_id');

        $query = Booking::select(
            'resort_id',
            'resorts.name as resort_name',
            DB::raw('DATE(check_in) as date'),
            DB::raw('COUNT(*) as bookings'),
            DB::raw('SUM(adults + children) as guests')
        )
        ->join('resorts', 'bookings.resort_id', '=', 'resorts.id')
        ->where('bookings.status', 'confirmed')
        ->whereBetween('bookings.check_in', [$startDate, $endDate]);

        if ($resortId) {
            $query->where('resort_id', $resortId);
        }

        $occupancy = $query->groupBy('resort_id', 'resorts.name', 'date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'occupancy' => $occupancy,
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'resort_id' => $resortId,
            ],
        ]);
    }

    /**
     * Get notification statistics
     */
    public function notificationStats(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date', now()->subMonth()));
        $endDate = Carbon::parse($request->get('end_date', now()));

        $stats = $this->notificationService->getNotificationStats($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Calculate growth rate between two values
     */
    protected function calculateGrowthRate($previous, $current)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Calculate conversion rate
     */
    protected function calculateConversionRate($startDate, $endDate)
    {
        $pageViews = AuditLog::where('action', 'page_view')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $bookings = Booking::whereBetween('created_at', [$startDate, $endDate])->count();

        return $pageViews > 0 ? round(($bookings / $pageViews) * 100, 2) : 0;
    }

    /**
     * Calculate error rate
     */
    protected function calculateErrorRate($startDate, $endDate)
    {
        $totalRequests = AuditLog::where('action', 'api_request')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $errorRequests = AuditLog::where('action', 'api_request')
            ->where('severity', 'high')
            ->orWhere('severity', 'critical')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return $totalRequests > 0 ? round(($errorRequests / $totalRequests) * 100, 2) : 0;
    }

    /**
     * Export dashboard data
     */
    public function exportData(Request $request)
    {
        $format = $request->get('format', 'json'); // json, csv, excel
        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();

        $data = [
            'summary' => $this->getSummaryStats($startDate, $endDate),
            'revenue' => $this->getRevenueData($startDate, $endDate),
            'bookings' => $this->getBookingData($startDate, $endDate),
            'guests' => $this->getGuestData($startDate, $endDate),
            'exported_at' => now()->toISOString(),
            'period' => $period,
        ];

        switch ($format) {
            case 'csv':
                return $this->exportToCSV($data);
            case 'excel':
                return $this->exportToExcel($data);
            default:
                return response()->json([
                    'success' => true,
                    'data' => $data,
                ]);
        }
    }

    /**
     * Export data to CSV format
     */
    protected function exportToCSV($data)
    {
        $filename = 'dashboard_report_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Write summary data
            fputcsv($file, ['Metric', 'Current Period', 'Previous Period', 'Growth %']);
            fputcsv($file, ['Total Bookings', $data['summary']['current']['total_bookings'], $data['summary']['previous']['total_bookings'], $data['summary']['growth']['bookings']]);
            fputcsv($file, ['Total Revenue', $data['summary']['current']['total_revenue'], $data['summary']['previous']['total_revenue'], $data['summary']['growth']['revenue']]);
            fputcsv($file, ['Total Guests', $data['summary']['current']['total_guests'], $data['summary']['previous']['total_guests'], $data['summary']['growth']['guests']]);
            
            fputcsv($file, []); // Empty row
            
            // Write revenue by resort
            fputcsv($file, ['Resort Revenue Breakdown']);
            fputcsv($file, ['Resort Name', 'Revenue', 'Transactions']);
            foreach ($data['revenue']['by_resort'] as $resort) {
                fputcsv($file, [$resort->resort_name, $resort->revenue, $resort->transactions]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export data to Excel format (would require additional library)
     */
    protected function exportToExcel($data)
    {
        // This would require a library like PhpSpreadsheet
        // For now, return JSON with a note
        return response()->json([
            'success' => false,
            'message' => 'Excel export requires additional package installation (phpoffice/phpspreadsheet)',
            'data' => $data,
        ]);
    }
}
