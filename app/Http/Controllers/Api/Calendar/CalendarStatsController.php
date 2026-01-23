<?php

namespace App\Http\Controllers\Api\Calendar;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\CalendarStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CalendarStatsController extends Controller
{
    protected $statsService;

    public function __construct(CalendarStatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * Get overall calendar statistics
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month'); // week, month, year, custom
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        if ($period === 'custom' && (!$startDate || !$endDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Start date and end date are required for custom period'
            ], 422);
        }

        $stats = $this->statsService->getOverviewStats($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user_id' => $userId
            ]
        ]);
    }

    /**
     * Get upcoming events statistics
     */
    public function upcoming(Request $request)
    {
        $userId = auth()->id();
        $days = $request->input('days', 7);
        $limit = $request->input('limit', 10);

        $upcomingStats = $this->statsService->getUpcomingStats($userId, $days, $limit);

        return response()->json([
            'success' => true,
            'data' => $upcomingStats,
            'meta' => [
                'days_ahead' => $days,
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Get busy days statistics
     */
    public function busyDays(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $limit = $request->input('limit', 10);
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $busyDays = $this->statsService->getBusyDays($userId, $period, $limit, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $busyDays,
            'meta' => [
                'period' => $period,
                'limit' => $limit,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get event type distribution
     */
    public function typeDistribution(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $distribution = $this->statsService->getEventTypeDistribution($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $distribution,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get meeting platform usage statistics
     */
    public function platformUsage(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $platformStats = $this->statsService->getPlatformUsage($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $platformStats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get event duration statistics
     */
    public function durationStats(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $durationStats = $this->statsService->getDurationStats($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $durationStats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get attendance statistics (for shared events)
     */
    public function attendanceStats(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $attendanceStats = $this->statsService->getAttendanceStats($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $attendanceStats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get time-based statistics (hourly, weekly patterns)
     */
    public function timePatterns(Request $request)
    {
        $userId = auth()->id();
        $type = $request->input('type', 'hourly'); // hourly, daily, weekly
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $timePatterns = $this->statsService->getTimePatterns($userId, $type, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $timePatterns,
            'meta' => [
                'type' => $type,
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get productivity statistics (time spent in meetings vs free time)
     */
    public function productivity(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'week');
        $startDate = $request->input('start_date', now()->startOfWeek());
        $endDate = $request->input('end_date', now()->endOfWeek());

        $productivityStats = $this->statsService->getProductivityStats($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $productivityStats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get comparison statistics (current vs previous period)
     */
    public function comparison(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $metric = $request->input('metric', 'events_count'); // events_count, duration, meetings, etc.

        $comparison = $this->statsService->getPeriodComparison($userId, $period, $metric);

        return response()->json([
            'success' => true,
            'data' => $comparison,
            'meta' => [
                'period' => $period,
                'metric' => $metric
            ]
        ]);
    }

    /**
     * Get user's most frequent collaborators
     */
    public function topCollaborators(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $limit = $request->input('limit', 10);
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $collaborators = $this->statsService->getTopCollaborators($userId, $period, $limit, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $collaborators,
            'meta' => [
                'period' => $period,
                'limit' => $limit,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get events by location statistics
     */
    public function locationStats(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $limit = $request->input('limit', 10);
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $locationStats = $this->statsService->getLocationStats($userId, $period, $limit, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $locationStats,
            'meta' => [
                'period' => $period,
                'limit' => $limit,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get recurring events statistics
     */
    public function recurringStats(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $recurringStats = $this->statsService->getRecurringEventsStats($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $recurringStats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get events with images statistics
     */
    public function mediaStats(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $mediaStats = $this->statsService->getMediaStats($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $mediaStats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get event status statistics
     */
    public function statusStats(Request $request)
    {
        $userId = auth()->id();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $statusStats = $this->statsService->getStatusStats($userId, $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $statusStats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get admin statistics (for admin users only)
     */
    public function adminStats(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $adminStats = $this->statsService->getAdminStats($period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $adminStats,
            'meta' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get custom statistics based on filters
     */
    public function custom(Request $request)
    {
        $userId = auth()->id();
        
        $request->validate([
            'metrics' => ['required', 'array'],
            'metrics.*' => ['in:events_count,duration_sum,avg_duration,max_attendees,unique_collaborators'],
            'filters' => ['nullable', 'array'],
            'group_by' => ['nullable', 'in:day,week,month,type,status,platform'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date']
        ]);

        $customStats = $this->statsService->getCustomStats(
            $userId,
            $request->input('metrics'),
            $request->input('filters', []),
            $request->input('group_by'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'success' => true,
            'data' => $customStats,
            'meta' => [
                'metrics' => $request->input('metrics'),
                'filters' => $request->input('filters', []),
                'group_by' => $request->input('group_by'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date')
            ]
        ]);
    }

    /**
     * Export statistics as CSV or JSON
     */
    public function export(Request $request)
    {
        $userId = auth()->id();
        $format = $request->input('format', 'json'); // json, csv
        $type = $request->input('type', 'overview'); // overview, upcoming, busy_days, etc.
        
        $stats = $this->statsService->getStatsForExport($userId, $type, $request->all());

        if ($format === 'csv') {
            return $this->exportAsCsv($stats, $type);
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'type' => $type,
                'format' => $format,
                'exported_at' => now()->toIso8601String()
            ]
        ]);
    }

    private function exportAsCsv($stats, $type)
    {
        $filename = "calendar_stats_{$type}_" . now()->format('Y-m-d') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($stats) {
            $file = fopen('php://output', 'w');
            
            // Write headers
            if (isset($stats[0]) && is_array($stats[0])) {
                fputcsv($file, array_keys($stats[0]));
            }
            
            // Write data
            foreach ($stats as $row) {
                fputcsv($file, array_values($row));
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}