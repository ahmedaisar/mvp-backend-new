<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Transaction;
use App\Models\GuestProfile;
use App\Models\Resort;
use App\Models\RoomType;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class ReportsController extends Controller
{
    /**
     * Get comprehensive revenue report
     */
    public function revenueReport(Request $request)
    {
        try {
            $startDate = Carbon::parse($request->get('start_date', now()->subMonth()));
            $endDate = Carbon::parse($request->get('end_date', now()));
            $groupBy = $request->get('group_by', 'day'); // day, week, month
            $resortId = $request->get('resort_id');

            $cacheKey = "revenue_report_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}_{$groupBy}_{$resortId}";
            $cacheDuration = now()->addMinutes(30);

            $report = Cache::remember($cacheKey, $cacheDuration, function () use ($startDate, $endDate, $groupBy, $resortId) {
                // Date format for grouping
                $dateFormat = match($groupBy) {
                    'week' => '%Y-%u',
                    'month' => '%Y-%m',
                    default => '%Y-%m-%d',
                };

                // Base query
                $query = Transaction::select(
                    DB::raw("DATE_FORMAT(transactions.created_at, '{$dateFormat}') as period"),
                    DB::raw('SUM(transactions.amount_usd) as revenue'),
                    DB::raw('SUM(transactions.amount_mvr) as revenue_mvr'),
                    DB::raw('COUNT(transactions.id) as transaction_count'),
                    DB::raw('AVG(transactions.amount_usd) as avg_transaction'),
                    'transactions.payment_method',
                    'resorts.name as resort_name',
                    'resorts.id as resort_id'
                )
                ->join('bookings', 'transactions.booking_id', '=', 'bookings.id')
                ->join('resorts', 'bookings.resort_id', '=', 'resorts.id')
                ->where('transactions.status', 'completed')
                ->whereBetween('transactions.created_at', [$startDate, $endDate]);

                if ($resortId) {
                    $query->where('resorts.id', $resortId);
                }

                $results = $query->groupBy('period', 'transactions.payment_method', 'resorts.id', 'resorts.name')
                    ->orderBy('period')
                    ->get();

                // Process results
                $revenueData = [];
                $paymentMethodBreakdown = [];
                $resortBreakdown = [];
                $totalRevenue = 0;
                $totalTransactions = 0;

                foreach ($results as $result) {
                    // Period data
                    if (!isset($revenueData[$result->period])) {
                        $revenueData[$result->period] = [
                            'period' => $result->period,
                            'revenue' => 0,
                            'revenue_mvr' => 0,
                            'transactions' => 0,
                        ];
                    }

                    $revenueData[$result->period]['revenue'] += $result->revenue;
                    $revenueData[$result->period]['revenue_mvr'] += $result->revenue_mvr;
                    $revenueData[$result->period]['transactions'] += $result->transaction_count;

                    // Payment method breakdown
                    if (!isset($paymentMethodBreakdown[$result->payment_method])) {
                        $paymentMethodBreakdown[$result->payment_method] = [
                            'method' => $result->payment_method,
                            'revenue' => 0,
                            'transactions' => 0,
                        ];
                    }

                    $paymentMethodBreakdown[$result->payment_method]['revenue'] += $result->revenue;
                    $paymentMethodBreakdown[$result->payment_method]['transactions'] += $result->transaction_count;

                    // Resort breakdown
                    if (!isset($resortBreakdown[$result->resort_id])) {
                        $resortBreakdown[$result->resort_id] = [
                            'resort_id' => $result->resort_id,
                            'resort_name' => $result->resort_name,
                            'revenue' => 0,
                            'transactions' => 0,
                        ];
                    }

                    $resortBreakdown[$result->resort_id]['revenue'] += $result->revenue;
                    $resortBreakdown[$result->resort_id]['transactions'] += $result->transaction_count;

                    $totalRevenue += $result->revenue;
                    $totalTransactions += $result->transaction_count;
                }

                return [
                    'period_data' => array_values($revenueData),
                    'payment_methods' => array_values($paymentMethodBreakdown),
                    'resorts' => array_values($resortBreakdown),
                    'totals' => [
                        'revenue' => $totalRevenue,
                        'transactions' => $totalTransactions,
                        'average_transaction' => $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'report' => $report,
                    'parameters' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString(),
                        'group_by' => $groupBy,
                        'resort_id' => $resortId,
                    ],
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating revenue report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get occupancy analytics report
     */
    public function occupancyReport(Request $request)
    {
        try {
            $startDate = Carbon::parse($request->get('start_date', now()));
            $endDate = Carbon::parse($request->get('end_date', now()->addMonth()));
            $resortId = $request->get('resort_id');

            $cacheKey = "occupancy_report_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}_{$resortId}";
            $cacheDuration = now()->addMinutes(15);

            $report = Cache::remember($cacheKey, $cacheDuration, function () use ($startDate, $endDate, $resortId) {
                // Get occupancy data
                $query = Booking::select(
                    'bookings.resort_id',
                    'resorts.name as resort_name',
                    DB::raw('DATE(bookings.check_in) as date'),
                    DB::raw('COUNT(bookings.id) as bookings_count'),
                    DB::raw('SUM(bookings.adults + bookings.children) as total_guests'),
                    'room_types.name as room_type',
                    'room_types.id as room_type_id'
                )
                ->join('resorts', 'bookings.resort_id', '=', 'resorts.id')
                ->join('rate_plans', 'bookings.rate_plan_id', '=', 'rate_plans.id')
                ->join('room_types', 'rate_plans.room_type_id', '=', 'room_types.id')
                ->where('bookings.status', 'confirmed')
                ->whereBetween('bookings.check_in', [$startDate, $endDate]);

                if ($resortId) {
                    $query->where('bookings.resort_id', $resortId);
                }

                $occupancyData = $query->groupBy('bookings.resort_id', 'resorts.name', 'date', 'room_types.id', 'room_types.name')
                    ->orderBy('date')
                    ->get();

                // Process data
                $resortOccupancy = [];
                $dailyOccupancy = [];
                $roomTypeOccupancy = [];

                foreach ($occupancyData as $data) {
                    // Resort occupancy
                    if (!isset($resortOccupancy[$data->resort_id])) {
                        $resortOccupancy[$data->resort_id] = [
                            'resort_id' => $data->resort_id,
                            'resort_name' => $data->resort_name,
                            'total_bookings' => 0,
                            'total_guests' => 0,
                            'occupied_nights' => 0,
                        ];
                    }

                    $resortOccupancy[$data->resort_id]['total_bookings'] += $data->bookings_count;
                    $resortOccupancy[$data->resort_id]['total_guests'] += $data->total_guests;
                    $resortOccupancy[$data->resort_id]['occupied_nights']++;

                    // Daily occupancy
                    if (!isset($dailyOccupancy[$data->date])) {
                        $dailyOccupancy[$data->date] = [
                            'date' => $data->date,
                            'total_bookings' => 0,
                            'total_guests' => 0,
                        ];
                    }

                    $dailyOccupancy[$data->date]['total_bookings'] += $data->bookings_count;
                    $dailyOccupancy[$data->date]['total_guests'] += $data->total_guests;

                    // Room type occupancy
                    $key = $data->resort_id . '_' . $data->room_type_id;
                    if (!isset($roomTypeOccupancy[$key])) {
                        $roomTypeOccupancy[$key] = [
                            'resort_id' => $data->resort_id,
                            'resort_name' => $data->resort_name,
                            'room_type_id' => $data->room_type_id,
                            'room_type' => $data->room_type,
                            'bookings' => 0,
                            'guests' => 0,
                        ];
                    }

                    $roomTypeOccupancy[$key]['bookings'] += $data->bookings_count;
                    $roomTypeOccupancy[$key]['guests'] += $data->total_guests;
                }

                return [
                    'by_resort' => array_values($resortOccupancy),
                    'by_date' => array_values($dailyOccupancy),
                    'by_room_type' => array_values($roomTypeOccupancy),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'report' => $report,
                    'parameters' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString(),
                        'resort_id' => $resortId,
                    ],
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating occupancy report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get guest analytics report
     */
    public function guestAnalytics(Request $request)
    {
        try {
            $startDate = Carbon::parse($request->get('start_date', now()->subMonth()));
            $endDate = Carbon::parse($request->get('end_date', now()));

            $cacheKey = "guest_analytics_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";
            $cacheDuration = now()->addMinutes(30);

            $report = Cache::remember($cacheKey, $cacheDuration, function () use ($startDate, $endDate) {
                // New vs Returning guests
                $guestStats = GuestProfile::select(
                    DB::raw('COUNT(DISTINCT guest_profiles.id) as total_guests'),
                    DB::raw('COUNT(DISTINCT CASE WHEN guest_profiles.created_at BETWEEN ? AND ? THEN guest_profiles.id END) as new_guests'),
                    DB::raw('AVG(DATEDIFF(NOW(), guest_profiles.created_at)) as avg_customer_age_days')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->first();

                // Guest demographics
                $demographics = GuestProfile::select(
                    'country',
                    DB::raw('COUNT(*) as count')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('country')
                ->groupBy('country')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

                // Booking behavior
                $bookingBehavior = Booking::select(
                    DB::raw('AVG(DATEDIFF(check_in, created_at)) as avg_booking_lead_time'),
                    DB::raw('AVG(adults + children) as avg_party_size'),
                    DB::raw('AVG(DATEDIFF(check_out, check_in)) as avg_stay_duration'),
                    DB::raw('COUNT(*) as total_bookings')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->first();

                // Guest lifetime value
                $lifetimeValue = DB::table('guest_profiles')
                    ->select(
                        DB::raw('AVG(total_spent) as avg_lifetime_value'),
                        DB::raw('MAX(total_spent) as max_lifetime_value'),
                        DB::raw('MIN(total_spent) as min_lifetime_value')
                    )
                    ->whereRaw('total_spent > 0')
                    ->first();

                // Repeat customers
                $repeatCustomers = GuestProfile::select(
                    DB::raw('COUNT(*) as guests_with_multiple_bookings')
                )
                ->whereHas('bookings', function($query) {}, '>', 1)
                ->first();

                return [
                    'guest_overview' => [
                        'total_guests' => $guestStats->total_guests,
                        'new_guests' => $guestStats->new_guests,
                        'returning_guests' => $guestStats->total_guests - $guestStats->new_guests,
                        'retention_rate' => $guestStats->total_guests > 0 ? 
                            round((($guestStats->total_guests - $guestStats->new_guests) / $guestStats->total_guests) * 100, 2) : 0,
                        'avg_customer_age_days' => round($guestStats->avg_customer_age_days, 0),
                    ],
                    'demographics' => $demographics,
                    'booking_behavior' => [
                        'avg_booking_lead_time' => round($bookingBehavior->avg_booking_lead_time, 1),
                        'avg_party_size' => round($bookingBehavior->avg_party_size, 1),
                        'avg_stay_duration' => round($bookingBehavior->avg_stay_duration, 1),
                        'total_bookings' => $bookingBehavior->total_bookings,
                    ],
                    'lifetime_value' => [
                        'average' => round($lifetimeValue->avg_lifetime_value ?? 0, 2),
                        'maximum' => round($lifetimeValue->max_lifetime_value ?? 0, 2),
                        'minimum' => round($lifetimeValue->min_lifetime_value ?? 0, 2),
                    ],
                    'loyalty_metrics' => [
                        'repeat_customers' => $repeatCustomers->guests_with_multiple_bookings,
                        'repeat_customer_rate' => $guestStats->total_guests > 0 ? 
                            round(($repeatCustomers->guests_with_multiple_bookings / $guestStats->total_guests) * 100, 2) : 0,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'report' => $report,
                    'parameters' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString(),
                    ],
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating guest analytics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance metrics report
     */
    public function performanceMetrics(Request $request)
    {
        try {
            $startDate = Carbon::parse($request->get('start_date', now()->subDays(7)));
            $endDate = Carbon::parse($request->get('end_date', now()));

            $cacheKey = "performance_metrics_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";
            $cacheDuration = now()->addMinutes(10);

            $report = Cache::remember($cacheKey, $cacheDuration, function () use ($startDate, $endDate) {
                // Conversion metrics
                $conversionData = AuditLog::select(
                    DB::raw('COUNT(CASE WHEN action = "page_view" THEN 1 END) as page_views'),
                    DB::raw('COUNT(CASE WHEN action = "booking_started" THEN 1 END) as booking_started'),
                    DB::raw('COUNT(CASE WHEN action = "booking_completed" THEN 1 END) as bookings_completed')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->first();

                // System performance
                $systemPerformance = AuditLog::select(
                    DB::raw('AVG(CASE WHEN action = "api_request" THEN response_time END) as avg_response_time'),
                    DB::raw('COUNT(CASE WHEN severity = "high" OR severity = "critical" THEN 1 END) as error_count'),
                    DB::raw('COUNT(*) as total_requests')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->first();

                // Payment success rate
                $paymentMetrics = Transaction::select(
                    DB::raw('COUNT(*) as total_transactions'),
                    DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as successful_transactions'),
                    DB::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_transactions'),
                    DB::raw('AVG(amount_usd) as avg_transaction_amount')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->first();

                // Calculate rates
                $conversionRate = $conversionData->page_views > 0 ? 
                    round(($conversionData->bookings_completed / $conversionData->page_views) * 100, 2) : 0;

                $bookingCompletionRate = $conversionData->booking_started > 0 ? 
                    round(($conversionData->bookings_completed / $conversionData->booking_started) * 100, 2) : 0;

                $paymentSuccessRate = $paymentMetrics->total_transactions > 0 ? 
                    round(($paymentMetrics->successful_transactions / $paymentMetrics->total_transactions) * 100, 2) : 0;

                $errorRate = $systemPerformance->total_requests > 0 ? 
                    round(($systemPerformance->error_count / $systemPerformance->total_requests) * 100, 2) : 0;

                return [
                    'conversion_metrics' => [
                        'page_views' => $conversionData->page_views,
                        'booking_started' => $conversionData->booking_started,
                        'bookings_completed' => $conversionData->bookings_completed,
                        'conversion_rate' => $conversionRate,
                        'booking_completion_rate' => $bookingCompletionRate,
                    ],
                    'system_performance' => [
                        'avg_response_time_ms' => round($systemPerformance->avg_response_time ?? 0, 2),
                        'total_requests' => $systemPerformance->total_requests,
                        'error_count' => $systemPerformance->error_count,
                        'error_rate' => $errorRate,
                        'uptime_percentage' => max(0, 100 - $errorRate), // Simplified uptime calculation
                    ],
                    'payment_metrics' => [
                        'total_transactions' => $paymentMetrics->total_transactions,
                        'successful_transactions' => $paymentMetrics->successful_transactions,
                        'failed_transactions' => $paymentMetrics->failed_transactions,
                        'success_rate' => $paymentSuccessRate,
                        'avg_transaction_amount' => round($paymentMetrics->avg_transaction_amount ?? 0, 2),
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'report' => $report,
                    'parameters' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString(),
                    ],
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating performance metrics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export report data to various formats
     */
    public function exportReport(Request $request)
    {
        try {
            $reportType = $request->get('report_type'); // revenue, occupancy, guest_analytics, performance
            $format = $request->get('format', 'json'); // json, csv, excel
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // Get report data based on type
            $reportData = match($reportType) {
                'revenue' => $this->getReportData('revenue', $request),
                'occupancy' => $this->getReportData('occupancy', $request),
                'guest_analytics' => $this->getReportData('guest_analytics', $request),
                'performance' => $this->getReportData('performance', $request),
                default => throw new Exception('Invalid report type')
            };

            // Export based on format
            switch ($format) {
                case 'csv':
                    return $this->exportToCSV($reportData, $reportType);
                case 'excel':
                    return response()->json([
                        'success' => false,
                        'message' => 'Excel export requires additional package installation',
                    ], 501);
                default:
                    return response()->json([
                        'success' => true,
                        'data' => $reportData,
                        'export_info' => [
                            'report_type' => $reportType,
                            'format' => $format,
                            'exported_at' => now()->toISOString(),
                        ],
                    ]);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get report data (helper method)
     */
    protected function getReportData($type, $request)
    {
        $response = match($type) {
            'revenue' => $this->revenueReport($request),
            'occupancy' => $this->occupancyReport($request),
            'guest_analytics' => $this->guestAnalytics($request),
            'performance' => $this->performanceMetrics($request),
            default => response()->json(['success' => false])
        };

        $data = json_decode($response->getContent(), true);
        return $data['success'] ? $data['data'] : [];
    }

    /**
     * Export data to CSV format
     */
    protected function exportToCSV($data, $reportType)
    {
        $filename = $reportType . '_report_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data, $reportType) {
            $file = fopen('php://output', 'w');
            
            // Write header based on report type
            match($reportType) {
                'revenue' => $this->writeRevenueCSV($file, $data),
                'occupancy' => $this->writeOccupancyCSV($file, $data),
                'guest_analytics' => $this->writeGuestAnalyticsCSV($file, $data),
                'performance' => $this->writePerformanceCSV($file, $data),
                default => fputcsv($file, ['Error: Unknown report type'])
            };
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Write revenue data to CSV
     */
    protected function writeRevenueCSV($file, $data)
    {
        // Summary
        fputcsv($file, ['Revenue Report Summary']);
        fputcsv($file, ['Total Revenue', $data['report']['totals']['revenue']]);
        fputcsv($file, ['Total Transactions', $data['report']['totals']['transactions']]);
        fputcsv($file, ['Average Transaction', $data['report']['totals']['average_transaction']]);
        fputcsv($file, []);
        
        // Period data
        fputcsv($file, ['Period', 'Revenue', 'Revenue MVR', 'Transactions']);
        foreach ($data['report']['period_data'] as $period) {
            fputcsv($file, [
                $period['period'],
                $period['revenue'],
                $period['revenue_mvr'],
                $period['transactions']
            ]);
        }
    }

    /**
     * Write occupancy data to CSV
     */
    protected function writeOccupancyCSV($file, $data)
    {
        fputcsv($file, ['Occupancy Report']);
        fputcsv($file, []);
        
        // By resort
        fputcsv($file, ['Resort', 'Total Bookings', 'Total Guests', 'Occupied Nights']);
        foreach ($data['report']['by_resort'] as $resort) {
            fputcsv($file, [
                $resort['resort_name'],
                $resort['total_bookings'],
                $resort['total_guests'],
                $resort['occupied_nights']
            ]);
        }
    }

    /**
     * Write guest analytics data to CSV
     */
    protected function writeGuestAnalyticsCSV($file, $data)
    {
        fputcsv($file, ['Guest Analytics Report']);
        fputcsv($file, []);
        
        $overview = $data['report']['guest_overview'];
        fputcsv($file, ['Metric', 'Value']);
        fputcsv($file, ['Total Guests', $overview['total_guests']]);
        fputcsv($file, ['New Guests', $overview['new_guests']]);
        fputcsv($file, ['Returning Guests', $overview['returning_guests']]);
        fputcsv($file, ['Retention Rate %', $overview['retention_rate']]);
    }

    /**
     * Write performance data to CSV
     */
    protected function writePerformanceCSV($file, $data)
    {
        fputcsv($file, ['Performance Metrics Report']);
        fputcsv($file, []);
        
        $performance = $data['report'];
        fputcsv($file, ['Metric Category', 'Metric', 'Value']);
        
        // Conversion metrics
        foreach ($performance['conversion_metrics'] as $key => $value) {
            fputcsv($file, ['Conversion', $key, $value]);
        }
        
        // System performance
        foreach ($performance['system_performance'] as $key => $value) {
            fputcsv($file, ['System', $key, $value]);
        }
        
        // Payment metrics
        foreach ($performance['payment_metrics'] as $key => $value) {
            fputcsv($file, ['Payment', $key, $value]);
        }
    }
}
