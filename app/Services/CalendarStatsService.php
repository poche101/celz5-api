<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\CalendarSubscription;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CalendarStatsService
{
    public function getOverviewStats($userId, $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);
        
        $events = CalendarEvent::visibleTo($userId)
            ->whereBetween('start_time', [$start, $end])
            ->get();

        $totalEvents = $events->count();
        $meetingEvents = $events->where('type', 'meeting')->count();
        $allDayEvents = $events->where('is_all_day', true)->count();
        $recurringEvents = $events->where('is_recurring', true)->count();
        
        // Calculate total duration in hours
        $totalDuration = $events->sum(function ($event) {
            if ($event->is_all_day) {
                return 8; // Assume 8 hours for all-day events
            }
            return $event->start_time->diffInHours($event->end_time);
        });

        // Calculate average attendees
        $eventsWithAttendees = $events->filter(fn($e) => !empty($e->attendees));
        $avgAttendees = $eventsWithAttendees->isNotEmpty() 
            ? $eventsWithAttendees->avg(fn($e) => count($e->attendees))
            : 0;

        // Get busiest day
        $busiestDay = $this->getBusiestDay($events);

        return [
            'total_events' => $totalEvents,
            'meeting_events' => $meetingEvents,
            'all_day_events' => $allDayEvents,
            'recurring_events' => $recurringEvents,
            'total_duration_hours' => round($totalDuration, 2),
            'avg_attendees' => round($avgAttendees, 1),
            'busiest_day' => $busiestDay,
            'events_by_status' => $events->groupBy('status')->map->count(),
            'period_summary' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => $start->diffInDays($end) + 1
            ]
        ];
    }

    public function getUpcomingStats($userId, $days = 7, $limit = 10): array
    {
        $start = now();
        $end = now()->addDays($days);

        $upcomingEvents = CalendarEvent::visibleTo($userId)
            ->whereBetween('start_time', [$start, $end])
            ->with(['user', 'images'])
            ->orderBy('start_time', 'asc')
            ->limit($limit)
            ->get();

        $urgentEvents = $upcomingEvents->filter(function ($event) {
            // Events starting within 24 hours
            return $event->start_time->diffInHours(now()) <= 24;
        });

        $eventsByDay = $upcomingEvents->groupBy(function ($event) {
            return $event->start_time->format('Y-m-d');
        })->map->count();

        $meetingTypes = $upcomingEvents->where('type', 'meeting')
            ->groupBy('meeting_platform')
            ->map->count();

        return [
            'upcoming_events' => $upcomingEvents->values(),
            'urgent_events' => $urgentEvents->values(),
            'events_by_day' => $eventsByDay,
            'meeting_types' => $meetingTypes,
            'summary' => [
                'total_upcoming' => $upcomingEvents->count(),
                'total_urgent' => $urgentEvents->count(),
                'days_covered' => $days,
                'next_event' => $upcomingEvents->first()
            ]
        ];
    }

    public function getBusyDays($userId, $period = 'month', $limit = 10, $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $busyDays = CalendarEvent::visibleTo($userId)
            ->select(
                DB::raw('DATE(start_time) as date'),
                DB::raw('COUNT(*) as event_count'),
                DB::raw('SUM(TIMESTAMPDIFF(HOUR, start_time, end_time)) as total_hours')
            )
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('date')
            ->orderBy('event_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($day) {
                return [
                    'date' => $day->date,
                    'event_count' => $day->event_count,
                    'total_hours' => $day->total_hours ?? 0,
                    'is_weekend' => Carbon::parse($day->date)->isWeekend(),
                    'day_of_week' => Carbon::parse($day->date)->dayName
                ];
            });

        // Also get least busy days
        $quietDays = CalendarEvent::visibleTo($userId)
            ->select(
                DB::raw('DATE(start_time) as date'),
                DB::raw('COUNT(*) as event_count')
            )
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('date')
            ->orderBy('event_count', 'asc')
            ->limit($limit)
            ->get();

        $dateRange = CarbonPeriod::create($start, $end);
        $allDates = collect($dateRange->toArray());
        
        $daysWithEvents = $busyDays->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString());
        $daysWithoutEvents = $allDates->reject(function ($date) use ($daysWithEvents) {
            return $daysWithEvents->contains($date->toDateString());
        });

        return [
            'busiest_days' => $busyDays,
            'quietest_days' => $quietDays,
            'days_without_events' => $daysWithoutEvents->count(),
            'average_events_per_day' => $busyDays->isNotEmpty() 
                ? round($busyDays->avg('event_count'), 2)
                : 0,
            'stats' => [
                'max_events_per_day' => $busyDays->max('event_count') ?? 0,
                'min_events_per_day' => $quietDays->isNotEmpty() ? $quietDays->min('event_count') : 0,
                'total_days_with_events' => $busyDays->count(),
                'total_days_in_period' => $allDates->count()
            ]
        ];
    }

    public function getEventTypeDistribution($userId, $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $distribution = CalendarEvent::visibleTo($userId)
            ->select('type', DB::raw('COUNT(*) as count'))
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('type')
            ->orderBy('count', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->type => $item->count];
            });

        $totalEvents = $distribution->sum();

        $percentages = $distribution->map(function ($count) use ($totalEvents) {
            return $totalEvents > 0 ? round(($count / $totalEvents) * 100, 2) : 0;
        });

        // Get most common type
        $mostCommonType = $distribution->isNotEmpty() 
            ? $distribution->sortDesc()->keys()->first()
            : null;

        return [
            'distribution' => $distribution,
            'percentages' => $percentages,
            'total_events' => $totalEvents,
            'most_common_type' => $mostCommonType,
            'type_colors' => $this->getTypeColors($distribution->keys())
        ];
    }

    public function getPlatformUsage($userId, $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $platforms = CalendarEvent::visibleTo($userId)
            ->select('meeting_platform', DB::raw('COUNT(*) as meeting_count'))
            ->where('type', 'meeting')
            ->whereNotNull('meeting_platform')
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('meeting_platform')
            ->orderBy('meeting_count', 'desc')
            ->get();

        $totalMeetings = $platforms->sum('meeting_count');

        $platformData = $platforms->map(function ($platform) use ($totalMeetings) {
            return [
                'platform' => $platform->meeting_platform,
                'meeting_count' => $platform->meeting_count,
                'percentage' => $totalMeetings > 0 
                    ? round(($platform->meeting_count / $totalMeetings) * 100, 2)
                    : 0,
                'platform_name' => config("calendar.meeting_platforms.{$platform->meeting_platform}", $platform->meeting_platform)
            ];
        });

        // Get events without platform (in-person meetings)
        $inPersonCount = CalendarEvent::visibleTo($userId)
            ->where('type', 'meeting')
            ->whereNull('meeting_platform')
            ->whereBetween('start_time', [$start, $end])
            ->count();

        if ($inPersonCount > 0) {
            $platformData->prepend([
                'platform' => 'in_person',
                'meeting_count' => $inPersonCount,
                'percentage' => $totalMeetings > 0 
                    ? round(($inPersonCount / $totalMeetings) * 100, 2)
                    : 0,
                'platform_name' => 'In Person'
            ]);
        }

        return [
            'platform_usage' => $platformData,
            'total_meetings' => $totalMeetings + $inPersonCount,
            'most_used_platform' => $platformData->isNotEmpty() 
                ? $platformData->sortByDesc('meeting_count')->first()
                : null,
            'stats' => [
                'virtual_meetings' => $totalMeetings,
                'in_person_meetings' => $inPersonCount,
                'hybrid_meetings' => CalendarEvent::visibleTo($userId)
                    ->where('type', 'meeting')
                    ->whereNotNull('meeting_link')
                    ->whereNotNull('location')
                    ->whereBetween('start_time', [$start, $end])
                    ->count()
            ]
        ];
    }

    public function getDurationStats($userId, $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $events = CalendarEvent::visibleTo($userId)
            ->whereBetween('start_time', [$start, $end])
            ->where('is_all_day', false)
            ->get();

        $durations = $events->map(function ($event) {
            return $event->start_time->diffInMinutes($event->end_time);
        });

        return [
            'total_duration_minutes' => $durations->sum(),
            'average_duration_minutes' => $durations->isNotEmpty() ? round($durations->avg(), 2) : 0,
            'min_duration_minutes' => $durations->isNotEmpty() ? $durations->min() : 0,
            'max_duration_minutes' => $durations->isNotEmpty() ? $durations->max() : 0,
            'duration_distribution' => $this->getDurationDistribution($durations),
            'longest_event' => $events->isNotEmpty() 
                ? $events->sortByDesc(fn($e) => $e->start_time->diffInMinutes($e->end_time))->first()
                : null,
            'shortest_event' => $events->isNotEmpty() 
                ? $events->sortBy(fn($e) => $e->start_time->diffInMinutes($e->end_time))->first()
                : null
        ];
    }

    public function getAttendanceStats($userId, $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        // Events where user is the organizer
        $organizedEvents = CalendarEvent::where('user_id', $userId)
            ->whereBetween('start_time', [$start, $end])
            ->where('visibility', '!=', 'private')
            ->get();

        $totalAttendees = 0;
        $eventsWithAttendees = 0;
        $attendeeData = [];

        foreach ($organizedEvents as $event) {
            $attendeeCount = count($event->attendees ?? []) + $event->subscriptions()->count();
            if ($attendeeCount > 0) {
                $totalAttendees += $attendeeCount;
                $eventsWithAttendees++;
                
                $attendeeData[] = [
                    'event_id' => $event->id,
                    'title' => $event->title,
                    'attendee_count' => $attendeeCount,
                    'date' => $event->start_time->toDateString()
                ];
            }
        }

        // Events where user is attendee
        $attendedEvents = CalendarSubscription::where('user_id', $userId)
            ->where('status', 'accepted')
            ->whereHas('event', function ($query) use ($start, $end) {
                $query->whereBetween('start_time', [$start, $end]);
            })
            ->with('event')
            ->get();

        return [
            'organized_events' => [
                'total_events' => $organizedEvents->count(),
                'events_with_attendees' => $eventsWithAttendees,
                'total_attendees' => $totalAttendees,
                'avg_attendees_per_event' => $eventsWithAttendees > 0 
                    ? round($totalAttendees / $eventsWithAttendees, 2)
                    : 0,
                'events' => $attendeeData
            ],
            'attended_events' => [
                'total_events' => $attendedEvents->count(),
                'events' => $attendedEvents->map(function ($subscription) {
                    return [
                        'event_id' => $subscription->event->id,
                        'title' => $subscription->event->title,
                        'organizer' => $subscription->event->user->name,
                        'date' => $subscription->event->start_time->toDateString(),
                        'permission' => $subscription->permission
                    ];
                })
            ],
            'attendance_rate' => $organizedEvents->isNotEmpty() 
                ? round(($eventsWithAttendees / $organizedEvents->count()) * 100, 2)
                : 0
        ];
    }

    public function getTimePatterns($userId, $type = 'hourly', $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $events = CalendarEvent::visibleTo($userId)
            ->whereBetween('start_time', [$start, $end])
            ->where('is_all_day', false)
            ->get();

        switch ($type) {
            case 'hourly':
                $pattern = array_fill(0, 24, 0);
                foreach ($events as $event) {
                    $hour = $event->start_time->hour;
                    $pattern[$hour]++;
                }
                $data = collect($pattern)->map(function ($count, $hour) {
                    return [
                        'hour' => $hour,
                        'hour_label' => sprintf('%02d:00', $hour),
                        'event_count' => $count,
                        'is_peak' => $count > 0
                    ];
                })->values();
                $peakHour = $data->sortByDesc('event_count')->first();
                break;

            case 'daily':
                $pattern = array_fill(0, 7, 0);
                foreach ($events as $event) {
                    $day = $event->start_time->dayOfWeek;
                    $pattern[$day]++;
                }
                $data = collect($pattern)->map(function ($count, $day) {
                    return [
                        'day' => $day,
                        'day_name' => Carbon::create()->startOfWeek()->addDays($day)->dayName,
                        'event_count' => $count
                    ];
                })->values();
                $peakDay = $data->sortByDesc('event_count')->first();
                break;

            case 'weekly':
                $weeks = [];
                $current = $start->copy()->startOfWeek();
                while ($current <= $end) {
                    $weekEnd = $current->copy()->endOfWeek();
                    $weekEvents = $events->filter(function ($event) use ($current, $weekEnd) {
                        return $event->start_time->between($current, $weekEnd);
                    });
                    $weeks[] = [
                        'week_start' => $current->toDateString(),
                        'week_end' => $weekEnd->toDateString(),
                        'week_number' => $current->weekOfYear,
                        'event_count' => $weekEvents->count(),
                        'total_hours' => $weekEvents->sum(function ($event) {
                            return $event->start_time->diffInHours($event->end_time);
                        })
                    ];
                    $current->addWeek();
                }
                $data = collect($weeks);
                $peakWeek = $data->sortByDesc('event_count')->first();
                break;

            default:
                $data = collect();
                $peakHour = null;
        }

        return [
            'type' => $type,
            'data' => $data,
            'peak_period' => $peakHour ?? $peakDay ?? ($peakWeek ?? null),
            'stats' => [
                'total_periods' => $data->count(),
                'average_events_per_period' => $data->isNotEmpty() 
                    ? round($data->avg('event_count'), 2)
                    : 0,
                'busiest_period' => $data->isNotEmpty() 
                    ? $data->sortByDesc('event_count')->first()
                    : null,
                'quietest_period' => $data->isNotEmpty() 
                    ? $data->sortBy('event_count')->first()
                    : null
            ]
        ];
    }

    public function getProductivityStats($userId, $period = 'week', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $events = CalendarEvent::visibleTo($userId)
            ->whereBetween('start_time', [$start, $end])
            ->where('is_all_day', false)
            ->get();

        $totalMeetingTime = $events->where('type', 'meeting')->sum(function ($event) {
            return $event->start_time->diffInMinutes($event->end_time);
        });

        $totalWorkTime = $events->sum(function ($event) {
            return $event->start_time->diffInMinutes($event->end_time);
        });

        $workingDays = $start->diffInDaysFiltered(function ($date) {
            return !$date->isWeekend();
        }, $end) + 1;

        $averageDailyMeetingTime = $workingDays > 0 ? $totalMeetingTime / $workingDays : 0;
        $averageDailyWorkTime = $workingDays > 0 ? $totalWorkTime / $workingDays : 0;

        // Calculate free time (assuming 8-hour workday)
        $totalAvailableMinutes = $workingDays * 8 * 60;
        $freeTimeMinutes = $totalAvailableMinutes - $totalWorkTime;
        $productivityPercentage = $totalAvailableMinutes > 0 
            ? round(($totalWorkTime / $totalAvailableMinutes) * 100, 2)
            : 0;

        return [
            'time_breakdown' => [
                'meeting_time_minutes' => $totalMeetingTime,
                'non_meeting_time_minutes' => $totalWorkTime - $totalMeetingTime,
                'free_time_minutes' => max(0, $freeTimeMinutes),
                'total_scheduled_time_minutes' => $totalWorkTime
            ],
            'daily_averages' => [
                'meeting_time' => round($averageDailyMeetingTime, 2),
                'work_time' => round($averageDailyWorkTime, 2),
                'free_time' => round($freeTimeMinutes / $workingDays, 2)
            ],
            'productivity_metrics' => [
                'productivity_percentage' => $productivityPercentage,
                'meeting_intensity' => $totalWorkTime > 0 
                    ? round(($totalMeetingTime / $totalWorkTime) * 100, 2)
                    : 0,
                'efficiency_score' => $this->calculateEfficiencyScore($events),
                'focus_blocks' => $this->identifyFocusBlocks($events)
            ],
            'recommendations' => $this->generateProductivityRecommendations(
                $totalMeetingTime,
                $totalWorkTime,
                $freeTimeMinutes
            )
        ];
    }

    public function getPeriodComparison($userId, $period = 'month', $metric = 'events_count'): array
    {
        $currentStart = now()->startOf($period);
        $currentEnd = now()->endOf($period);
        
        $previousStart = now()->sub(1, $period)->startOf($period);
        $previousEnd = now()->sub(1, $period)->endOf($period);

        $currentStats = $this->getMetricValue($userId, $metric, $currentStart, $currentEnd);
        $previousStats = $this->getMetricValue($userId, $metric, $previousStart, $previousEnd);

        $difference = $currentStats - $previousStats;
        $percentageChange = $previousStats != 0 
            ? round(($difference / $previousStats) * 100, 2)
            : ($currentStats > 0 ? 100 : 0);

        return [
            'current_period' => [
                'start' => $currentStart->toDateString(),
                'end' => $currentEnd->toDateString(),
                'value' => $currentStats
            ],
            'previous_period' => [
                'start' => $previousStart->toDateString(),
                'end' => $previousEnd->toDateString(),
                'value' => $previousStats
            ],
            'comparison' => [
                'difference' => $difference,
                'percentage_change' => $percentageChange,
                'trend' => $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'stable'),
                'is_improvement' => $this->isImprovement($metric, $difference)
            ],
            'metric_info' => [
                'name' => $metric,
                'label' => $this->getMetricLabel($metric),
                'unit' => $this->getMetricUnit($metric)
            ]
        ];
    }

    public function getTopCollaborators($userId, $period = 'month', $limit = 10, $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        // Get events where user is organizer and has attendees
        $organizedEvents = CalendarEvent::where('user_id', $userId)
            ->whereBetween('start_time', [$start, $end])
            ->whereNotNull('attendees')
            ->get();

        $collaboratorCounts = [];
        
        foreach ($organizedEvents as $event) {
            foreach ($event->attendees as $email) {
                $user = User::where('email', $email)->first();
                if ($user && $user->id != $userId) {
                    $collaboratorCounts[$user->id] = [
                        'user' => $user,
                        'count' => ($collaboratorCounts[$user->id]['count'] ?? 0) + 1,
                        'last_collaboration' => $event->start_time
                    ];
                }
            }
        }

        // Also count from subscriptions
        $subscriptions = CalendarSubscription::where('user_id', $userId)
            ->where('status', 'accepted')
            ->whereHas('event', function ($query) use ($start, $end) {
                $query->whereBetween('start_time', [$start, $end]);
            })
            ->with(['event.user'])
            ->get();

        foreach ($subscriptions as $subscription) {
            $organizerId = $subscription->event->user_id;
            if ($organizerId != $userId) {
                $organizer = User::find($organizerId);
                if ($organizer) {
                    $collaboratorCounts[$organizerId] = [
                        'user' => $organizer,
                        'count' => ($collaboratorCounts[$organizerId]['count'] ?? 0) + 1,
                        'last_collaboration' => $subscription->event->start_time
                    ];
                }
            }
        }

        // Sort by collaboration count
        $sortedCollaborators = collect($collaboratorCounts)
            ->sortByDesc('count')
            ->take($limit)
            ->map(function ($data, $userId) {
                return [
                    'user_id' => $userId,
                    'name' => $data['user']->name,
                    'email' => $data['user']->email,
                    'collaboration_count' => $data['count'],
                    'last_collaboration' => $data['last_collaboration']->toDateString(),
                    'collaboration_frequency' => $this->getCollaborationFrequency($data['count'], $period)
                ];
            })
            ->values();

        return [
            'top_collaborators' => $sortedCollaborators,
            'total_unique_collaborators' => count($collaboratorCounts),
            'average_collaborations' => $sortedCollaborators->isNotEmpty() 
                ? round($sortedCollaborators->avg('collaboration_count'), 2)
                : 0,
            'collaboration_network' => [
                'density' => $this->calculateNetworkDensity($sortedCollaborators),
                'most_active' => $sortedCollaborators->first(),
                'new_collaborators' => $this->getNewCollaborators($userId, $start, $end)
            ]
        ];
    }

    public function getLocationStats($userId, $period = 'month', $limit = 10, $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $locations = CalendarEvent::visibleTo($userId)
            ->select('location', DB::raw('COUNT(*) as event_count'))
            ->whereNotNull('location')
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('location')
            ->orderBy('event_count', 'desc')
            ->limit($limit)
            ->get();

        $totalEventsWithLocation = $locations->sum('event_count');
        $virtualEvents = CalendarEvent::visibleTo($userId)
            ->whereNotNull('meeting_link')
            ->whereBetween('start_time', [$start, $end])
            ->count();

        $locationCategories = $locations->map(function ($location) use ($totalEventsWithLocation) {
            $category = $this->categorizeLocation($location->location);
            return [
                'location' => $location->location,
                'event_count' => $location->event_count,
                'percentage' => $totalEventsWithLocation > 0 
                    ? round(($location->event_count / $totalEventsWithLocation) * 100, 2)
                    : 0,
                'category' => $category,
                'is_virtual' => str_contains(strtolower($location->location), ['zoom', 'meet', 'teams', 'virtual'])
            ];
        });

        return [
            'locations' => $locationCategories,
            'summary' => [
                'total_locations' => $locations->count(),
                'most_common_location' => $locations->first(),
                'virtual_events' => $virtualEvents,
                'in_person_events' => $totalEventsWithLocation - $virtualEvents,
                'location_diversity' => $this->calculateLocationDiversity($locations)
            ],
            'location_categories' => $locationCategories->groupBy('category')->map->count()
        ];
    }

    public function getRecurringEventsStats($userId, $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $recurringEvents = CalendarEvent::visibleTo($userId)
            ->where('is_recurring', true)
            ->whereBetween('start_time', [$start, $end])
            ->get();

        $recurrenceTypes = $recurringEvents->groupBy('recurrence')->map->count();
        
        $totalOccurrences = $recurringEvents->sum(function ($event) use ($start, $end) {
            return $this->calculateOccurrences($event, $start, $end);
        });

        $longestRunning = $recurringEvents->sortByDesc('created_at')->first();
        $mostFrequent = $recurringEvents->sortByDesc(function ($event) {
            return $this->getRecurrenceFrequency($event->recurrence);
        })->first();

        return [
            'recurring_events' => [
                'total_unique' => $recurringEvents->count(),
                'total_occurrences' => $totalOccurrences,
                'average_occurrences_per_event' => $recurringEvents->isNotEmpty() 
                    ? round($totalOccurrences / $recurringEvents->count(), 2)
                    : 0
            ],
            'recurrence_types' => $recurrenceTypes,
            'insights' => [
                'longest_running_event' => $longestRunning,
                'most_frequent_event' => $mostFrequent,
                'saved_time' => $this->calculateTimeSaved($recurringEvents),
                'consistency_score' => $this->calculateConsistencyScore($recurringEvents)
            ],
            'recommendations' => $this->generateRecurrenceRecommendations($recurringEvents)
        ];
    }

    public function getMediaStats($userId, $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $eventsWithImages = CalendarEvent::visibleTo($userId)
            ->whereHas('images')
            ->whereBetween('start_time', [$start, $end])
            ->withCount('images')
            ->get();

        $totalImages = $eventsWithImages->sum('images_count');
        $imageFormats = DB::table('calendar_event_images')
            ->join('calendar_events', 'calendar_event_images.calendar_event_id', '=', 'calendar_events.id')
            ->whereBetween('calendar_events.start_time', [$start, $end])
            ->whereIn('calendar_events.id', function ($query) use ($userId) {
                $query->select('id')
                    ->from('calendar_events')
                    ->whereVisibleTo($userId);
            })
            ->select('mime_type', DB::raw('COUNT(*) as count'))
            ->groupBy('mime_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->mime_type => $item->count];
            });

        $largestImage = DB::table('calendar_event_images')
            ->join('calendar_events', 'calendar_event_images.calendar_event_id', '=', 'calendar_events.id')
            ->whereBetween('calendar_events.start_time', [$start, $end])
            ->whereIn('calendar_events.id', function ($query) use ($userId) {
                $query->select('id')
                    ->from('calendar_events')
                    ->whereVisibleTo($userId);
            })
            ->orderBy('size', 'desc')
            ->first();

        $averageImagesPerEvent = $eventsWithImages->isNotEmpty() 
            ? round($totalImages / $eventsWithImages->count(), 2)
            : 0;

        return [
            'events_with_images' => $eventsWithImages->count(),
            'total_images' => $totalImages,
            'average_images_per_event' => $averageImagesPerEvent,
            'image_formats' => $imageFormats,
            'largest_image' => $largestImage ? [
                'size' => $largestImage->size,
                'mime_type' => $largestImage->mime_type,
                'event_id' => $largestImage->calendar_event_id
            ] : null,
            'media_rich_events' => $eventsWithImages->where('images_count', '>', 3)->count(),
            'storage_usage' => [
                'total_mb' => round(DB::table('calendar_event_images')
                    ->join('calendar_events', 'calendar_event_images.calendar_event_id', '=', 'calendar_events.id')
                    ->whereBetween('calendar_events.start_time', [$start, $end])
                    ->whereIn('calendar_events.id', function ($query) use ($userId) {
                        $query->select('id')
                            ->from('calendar_events')
                            ->whereVisibleTo($userId);
                    })
                    ->sum('calendar_event_images.size') / 1024 / 1024, 2),
                'average_mb_per_image' => $totalImages > 0 
                    ? round(DB::table('calendar_event_images')
                        ->join('calendar_events', 'calendar_event_images.calendar_event_id', '=', 'calendar_events.id')
                        ->whereBetween('calendar_events.start_time', [$start, $end])
                        ->whereIn('calendar_events.id', function ($query) use ($userId) {
                            $query->select('id')
                                ->from('calendar_events')
                                ->whereVisibleTo($userId);
                        })
                        ->avg('calendar_event_images.size') / 1024 / 1024, 2)
                    : 0
            ]
        ];
    }

    public function getStatusStats($userId, $period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        $statusCounts = CalendarEvent::visibleTo($userId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            });

        $totalEvents = $statusCounts->sum();
        $completionRate = $totalEvents > 0 
            ? round(($statusCounts['completed'] ?? 0) / $totalEvents * 100, 2)
            : 0;

        $cancellationRate = $totalEvents > 0 
            ? round(($statusCounts['cancelled'] ?? 0) / $totalEvents * 100, 2)
            : 0;

        // Get trends (compare with previous period)
        $previousStart = $start->copy()->sub(1, $period);
        $previousEnd = $end->copy()->sub(1, $period);
        
        $previousStatusCounts = CalendarEvent::visibleTo($userId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereBetween('start_time', [$previousStart, $previousEnd])
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            });

        $statusTrends = [];
        foreach ($statusCounts as $status => $count) {
            $previousCount = $previousStatusCounts[$status] ?? 0;
            $trend = $previousCount > 0 
                ? round(($count - $previousCount) / $previousCount * 100, 2)
                : ($count > 0 ? 100 : 0);
            
            $statusTrends[$status] = [
                'current' => $count,
                'previous' => $previousCount,
                'trend' => $trend,
                'direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'stable')
            ];
        }

        return [
            'status_distribution' => $statusCounts,
            'status_percentages' => $statusCounts->map(function ($count) use ($totalEvents) {
                return $totalEvents > 0 ? round(($count / $totalEvents) * 100, 2) : 0;
            }),
            'key_metrics' => [
                'completion_rate' => $completionRate,
                'cancellation_rate' => $cancellationRate,
                'in_progress_rate' => $totalEvents > 0 
                    ? round(($statusCounts['in_progress'] ?? 0) / $totalEvents * 100, 2)
                    : 0,
                'scheduled_rate' => $totalEvents > 0 
                    ? round(($statusCounts['scheduled'] ?? 0) / $totalEvents * 100, 2)
                    : 0
            ],
            'trends' => $statusTrends,
            'insights' => [
                'most_common_status' => $statusCounts->isNotEmpty() 
                    ? $statusCounts->sortDesc()->keys()->first()
                    : null,
                'improvement_areas' => $this->identifyImprovementAreas($statusCounts, $statusTrends),
                'recommendations' => $this->generateStatusRecommendations($statusCounts, $completionRate)
            ]
        ];
    }

    public function getAdminStats($period = 'month', $startDate = null, $endDate = null): array
    {
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        // Total events across all users
        $totalEvents = CalendarEvent::whereBetween('start_time', [$start, $end])->count();
        
        // Active users (users with events)
        $activeUsers = CalendarEvent::whereBetween('start_time', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        // Events by type
        $eventsByType = CalendarEvent::select('type', DB::raw('COUNT(*) as count'))
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->type => $item->count];
            });

        // Events by visibility
        $eventsByVisibility = CalendarEvent::select('visibility', DB::raw('COUNT(*) as count'))
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('visibility')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->visibility => $item->count];
            });

        // Top event creators
        $topCreators = CalendarEvent::select('user_id', DB::raw('COUNT(*) as event_count'))
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('user_id')
            ->orderBy('event_count', 'desc')
            ->limit(10)
            ->with('user')
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->user_id,
                    'name' => $item->user->name,
                    'email' => $item->user->email,
                    'event_count' => $item->event_count
                ];
            });

        // Platform usage across all users
        $platformUsage = CalendarEvent::select('meeting_platform', DB::raw('COUNT(*) as count'))
            ->where('type', 'meeting')
            ->whereNotNull('meeting_platform')
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('meeting_platform')
            ->get();

        // Growth metrics (compare with previous period)
        $previousStart = $start->copy()->sub(1, $period);
        $previousEnd = $end->copy()->sub(1, $period);
        
        $previousTotalEvents = CalendarEvent::whereBetween('start_time', [$previousStart, $previousEnd])->count();
        $growthRate = $previousTotalEvents > 0 
            ? round(($totalEvents - $previousTotalEvents) / $previousTotalEvents * 100, 2)
            : ($totalEvents > 0 ? 100 : 0);

        return [
            'overall_metrics' => [
                'total_events' => $totalEvents,
                'active_users' => $activeUsers,
                'average_events_per_user' => $activeUsers > 0 ? round($totalEvents / $activeUsers, 2) : 0,
                'growth_rate' => $growthRate,
                'event_density' => round($totalEvents / ($start->diffInDays($end) + 1), 2)
            ],
            'distribution' => [
                'by_type' => $eventsByType,
                'by_visibility' => $eventsByVisibility,
                'by_status' => CalendarEvent::select('status', DB::raw('COUNT(*) as count'))
                    ->whereBetween('start_time', [$start, $end])
                    ->groupBy('status')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->status => $item->count];
                    })
            ],
            'top_performers' => [
                'creators' => $topCreators,
                'most_active_day' => $this->getMostActiveDay($start, $end),
                'most_used_platform' => $platformUsage->sortByDesc('count')->first()
            ],
            'platform_usage' => $platformUsage,
            'storage_metrics' => [
                'total_images' => DB::table('calendar_event_images')
                    ->join('calendar_events', 'calendar_event_images.calendar_event_id', '=', 'calendar_events.id')
                    ->whereBetween('calendar_events.start_time', [$start, $end])
                    ->count(),
                'total_storage_mb' => round(DB::table('calendar_event_images')
                    ->join('calendar_events', 'calendar_event_images.calendar_event_id', '=', 'calendar_events.id')
                    ->whereBetween('calendar_events.start_time', [$start, $end])
                    ->sum('calendar_event_images.size') / 1024 / 1024, 2)
            ],
            'engagement_metrics' => [
                'shared_events' => CalendarEvent::where('visibility', 'shared')
                    ->whereBetween('start_time', [$start, $end])
                    ->count(),
                'events_with_attendees' => CalendarEvent::whereNotNull('attendees')
                    ->whereBetween('start_time', [$start, $end])
                    ->count(),
                'average_attendees_per_event' => CalendarEvent::whereNotNull('attendees')
                    ->whereBetween('start_time', [$start, $end])
                    ->avg(DB::raw('JSON_LENGTH(attendees)'))
            ]
        ];
    }

    public function getCustomStats($userId, array $metrics, array $filters = [], ?string $groupBy, $startDate, $endDate): array
    {
        [$start, $end] = [$startDate, $endDate];

        $query = CalendarEvent::visibleTo($userId)
            ->whereBetween('start_time', [$start, $end]);

        // Apply filters
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        $selects = [];
        foreach ($metrics as $metric) {
            switch ($metric) {
                case 'events_count':
                    $selects[] = DB::raw('COUNT(*) as events_count');
                    break;
                case 'duration_sum':
                    $selects[] = DB::raw('SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as duration_sum');
                    break;
                case 'avg_duration':
                    $selects[] = DB::raw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration');
                    break;
                case 'max_attendees':
                    $selects[] = DB::raw('MAX(JSON_LENGTH(attendees)) as max_attendees');
                    break;
                case 'unique_collaborators':
                    // This would need a more complex query
                    break;
            }
        }

        if ($groupBy) {
            switch ($groupBy) {
                case 'day':
                    $query->addSelect(DB::raw('DATE(start_time) as period'));
                    $query->groupBy('period');
                    break;
                case 'week':
                    $query->addSelect(DB::raw('YEARWEEK(start_time) as period'));
                    $query->groupBy('period');
                    break;
                case 'month':
                    $query->addSelect(DB::raw('DATE_FORMAT(start_time, "%Y-%m") as period'));
                    $query->groupBy('period');
                    break;
                case 'type':
                    $query->addSelect('type as period');
                    $query->groupBy('type');
                    break;
                case 'status':
                    $query->addSelect('status as period');
                    $query->groupBy('status');
                    break;
                case 'platform':
                    $query->addSelect('meeting_platform as period');
                    $query->groupBy('meeting_platform');
                    break;
            }
        }

        $query->addSelect($selects);
        $results = $query->get();

        return [
            'metrics' => $metrics,
            'group_by' => $groupBy,
            'filters' => $filters,
            'results' => $results,
            'summary' => [
                'total_records' => $results->count(),
                'date_range' => [
                    'start' => $start,
                    'end' => $end,
                    'days' => Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1
                ]
            ]
        ];
    }

    public function getStatsForExport($userId, $type, $params = []): array
    {
        switch ($type) {
            case 'overview':
                return $this->getOverviewStats($userId, 
                    $params['period'] ?? 'month',
                    $params['start_date'] ?? null,
                    $params['end_date'] ?? null
                );
            case 'upcoming':
                return $this->getUpcomingStats($userId,
                    $params['days'] ?? 7,
                    $params['limit'] ?? 10
                );
            case 'busy_days':
                return $this->getBusyDays($userId,
                    $params['period'] ?? 'month',
                    $params['limit'] ?? 10,
                    $params['start_date'] ?? null,
                    $params['end_date'] ?? null
                );
            // Add more cases as needed
            default:
                return ['error' => 'Invalid export type'];
        }
    }

    // Helper Methods

    private function getDateRange($period, $startDate, $endDate): array
    {
        if ($startDate && $endDate) {
            return [Carbon::parse($startDate), Carbon::parse($endDate)];
        }

        switch ($period) {
            case 'week':
                return [now()->startOfWeek(), now()->endOfWeek()];
            case 'month':
                return [now()->startOfMonth(), now()->endOfMonth()];
            case 'year':
                return [now()->startOfYear(), now()->endOfYear()];
            case 'quarter':
                return [now()->startOfQuarter(), now()->endOfQuarter()];
            default:
                return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    private function getBusiestDay($events)
    {
        if ($events->isEmpty()) {
            return null;
        }

        $dayCounts = $events->groupBy(function ($event) {
            return $event->start_time->format('Y-m-d');
        })->map->count();

        $busiestDate = $dayCounts->sortDesc()->keys()->first();
        
        return [
            'date' => $busiestDate,
            'event_count' => $dayCounts[$busiestDate],
            'day_name' => Carbon::parse($busiestDate)->dayName,
            'events' => $events->where(
                fn($e) => $e->start_time->format('Y-m-d') === $busiestDate
            )->values()
        ];
    }

    private function getTypeColors($types)
    {
        $defaultColors = [
            'meeting' => '#3b82f6',
            'appointment' => '#10b981',
            'reminder' => '#f59e0b',
            'holiday' => '#ef4444',
            'event' => '#8b5cf6',
            'task' => '#64748b'
        ];

        return collect($types)->mapWithKeys(function ($type) use ($defaultColors) {
            return [$type => $defaultColors[$type] ?? '#6b7280'];
        })->toArray();
    }

    private function getDurationDistribution($durations)
    {
        $ranges = [
            '0-15 min' => [0, 15],
            '15-30 min' => [15, 30],
            '30-60 min' => [30, 60],
            '1-2 hours' => [60, 120],
            '2-4 hours' => [120, 240],
            '4+ hours' => [240, PHP_INT_MAX]
        ];

        $distribution = array_fill_keys(array_keys($ranges), 0);

        foreach ($durations as $duration) {
            foreach ($ranges as $label => [$min, $max]) {
                if ($duration >= $min && $duration < $max) {
                    $distribution[$label]++;
                    break;
                }
            }
        }

        return $distribution;
    }

    private function calculateEfficiencyScore($events)
    {
        if ($events->isEmpty()) {
            return 0;
        }

        $totalEvents = $events->count();
        $completedEvents = $events->where('status', 'completed')->count();
        $cancelledEvents = $events->where('status', 'cancelled')->count();
        
        $completionRate = $completedEvents / $totalEvents * 100;
        $cancellationRate = $cancelledEvents / $totalEvents * 100;
        
        // Efficiency score: completion rate minus cancellation rate
        $efficiency = $completionRate - $cancellationRate;
        
        return max(0, min(100, $efficiency));
    }

    private function identifyFocusBlocks($events)
    {
        // Sort events by start time
        $sortedEvents = $events->sortBy('start_time');
        
        $focusBlocks = [];
        $currentBlock = null;
        
        foreach ($sortedEvents as $event) {
            if ($currentBlock === null) {
                $currentBlock = [
                    'start' => $event->start_time,
                    'end' => $event->end_time,
                    'events' => [$event]
                ];
            } elseif ($event->start_time <= $currentBlock['end']->addMinutes(30)) {
                // Events within 30 minutes of each other are considered same focus block
                $currentBlock['end'] = max($currentBlock['end'], $event->end_time);
                $currentBlock['events'][] = $event;
            } else {
                $focusBlocks[] = $currentBlock;
                $currentBlock = [
                    'start' => $event->start_time,
                    'end' => $event->end_time,
                    'events' => [$event]
                ];
            }
        }
        
        if ($currentBlock !== null) {
            $focusBlocks[] = $currentBlock;
        }
        
        return collect($focusBlocks)->map(function ($block) {
            $duration = $block['start']->diffInMinutes($block['end']);
            return [
                'start' => $block['start']->toDateTimeString(),
                'end' => $block['end']->toDateTimeString(),
                'duration_minutes' => $duration,
                'event_count' => count($block['events']),
                'focus_score' => $this->calculateFocusScore($block['events'], $duration)
            ];
        })->sortByDesc('focus_score')->values();
    }

    private function calculateFocusScore($events, $totalDuration)
    {
        $eventCount = count($events);
        $meetingCount = collect($events)->where('type', 'meeting')->count();
        
        // Higher score for fewer meetings and more focused time
        $score = ($eventCount > 0) 
            ? (($totalDuration / $eventCount) * (1 - ($meetingCount / $eventCount))) 
            : 0;
        
        return round($score, 2);
    }

    private function generateProductivityRecommendations($meetingTime, $workTime, $freeTime)
    {
        $recommendations = [];
        
        $meetingPercentage = $workTime > 0 ? ($meetingTime / $workTime * 100) : 0;
        
        if ($meetingPercentage > 50) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'High meeting load (' . round($meetingPercentage, 1) . '%). Consider reducing meeting frequency.',
                'action' => 'Try implementing "no meeting" days or shorter meeting durations.'
            ];
        }
        
        if ($freeTime < 0) {
            $recommendations[] = [
                'type' => 'critical',
                'message' => 'You have no free time scheduled. This can lead to burnout.',
                'action' => 'Schedule dedicated breaks and personal time.'
            ];
        } elseif ($freeTime < 120) { // Less than 2 hours of free time
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Limited free time scheduled (' . round($freeTime / 60, 1) . ' hours).',
                'action' => 'Consider blocking focus time for deep work.'
            ];
        }
        
        if ($workTime > 8 * 60) { // More than 8 hours of work
            $recommendations[] = [
                'type' => 'info',
                'message' => 'You have ' . round($workTime / 60, 1) . ' hours of scheduled work.',
                'action' => 'Ensure you take regular breaks to maintain productivity.'
            ];
        }
        
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'Good balance between meetings, work, and free time.',
                'action' => 'Keep maintaining this healthy schedule!'
            ];
        }
        
        return $recommendations;
    }

    private function getMetricValue($userId, $metric, $start, $end)
    {
        $query = CalendarEvent::visibleTo($userId)
            ->whereBetween('start_time', [$start, $end]);

        switch ($metric) {
            case 'events_count':
                return $query->count();
            case 'duration_sum':
                return $query->sum(DB::raw('TIMESTAMPDIFF(MINUTE, start_time, end_time)'));
            case 'meetings_count':
                return $query->where('type', 'meeting')->count();
            case 'avg_attendees':
                return round($query->whereNotNull('attendees')
                    ->avg(DB::raw('JSON_LENGTH(attendees)')), 2);
            default:
                return 0;
        }
    }

    private function getMetricLabel($metric)
    {
        $labels = [
            'events_count' => 'Total Events',
            'duration_sum' => 'Total Duration',
            'meetings_count' => 'Total Meetings',
            'avg_attendees' => 'Average Attendees'
        ];
        
        return $labels[$metric] ?? $metric;
    }

    private function getMetricUnit($metric)
    {
        $units = [
            'events_count' => 'events',
            'duration_sum' => 'minutes',
            'meetings_count' => 'meetings',
            'avg_attendees' => 'people'
        ];
        
        return $units[$metric] ?? '';
    }

    private function isImprovement($metric, $difference)
    {
        // For some metrics, decrease is improvement (like cancellation rate)
        $negativeIsGood = ['cancellation_rate', 'meetings_count'];
        
        if (in_array($metric, $negativeIsGood)) {
            return $difference < 0;
        }
        
        // For most metrics, increase is improvement
        return $difference > 0;
    }

    private function getCollaborationFrequency($count, $period)
    {
        $periodDays = [
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            'year' => 365
        ];
        
        $days = $periodDays[$period] ?? 30;
        $frequency = $count / $days;
        
        if ($frequency >= 1) {
            return 'daily';
        } elseif ($frequency >= 0.5) {
            return 'every other day';
        } elseif ($frequency >= 0.14) { // ~once a week
            return 'weekly';
        } elseif ($frequency >= 0.033) { // ~once a month
            return 'monthly';
        } else {
            return 'occasionally';
        }
    }

    private function calculateNetworkDensity($collaborators)
    {
        if ($collaborators->isEmpty()) {
            return 0;
        }
        
        $totalPossibleConnections = ($collaborators->count() * ($collaborators->count() - 1)) / 2;
        
        // This is a simplified calculation
        // In reality, you'd need to track actual connections between collaborators
        $actualConnections = $collaborators->sum('collaboration_count') / 2;
        
        return $totalPossibleConnections > 0 
            ? round($actualConnections / $totalPossibleConnections * 100, 2)
            : 0;
    }

    private function getNewCollaborators($userId, $start, $end)
    {
        // Get collaborators from current period
        $currentCollaborators = $this->getCollaboratorIds($userId, $start, $end);
        
        // Get collaborators from previous period (last 30 days before start)
        $previousStart = $start->copy()->subDays(30);
        $previousCollaborators = $this->getCollaboratorIds($userId, $previousStart, $start);
        
        // New collaborators are those in current but not in previous
        $newCollaborators = array_diff($currentCollaborators, $previousCollaborators);
        
        return count($newCollaborators);
    }

    private function getCollaboratorIds($userId, $start, $end)
    {
        // This is a simplified version
        $events = CalendarEvent::where('user_id', $userId)
            ->whereBetween('start_time', [$start, $end])
            ->whereNotNull('attendees')
            ->get();
        
        $collaboratorIds = [];
        
        foreach ($events as $event) {
            foreach ($event->attendees as $email) {
                $user = User::where('email', $email)->first();
                if ($user && $user->id != $userId) {
                    $collaboratorIds[] = $user->id;
                }
            }
        }
        
        return array_unique($collaboratorIds);
    }

    private function categorizeLocation($location)
    {
        $location = strtolower($location);
        
        if (str_contains($location, ['zoom', 'meet', 'teams', 'webex', 'virtual'])) {
            return 'virtual';
        } elseif (str_contains($location, ['office', 'work', 'company', 'corporate'])) {
            return 'office';
        } elseif (str_contains($location, ['home', 'residence', 'house'])) {
            return 'home';
        } elseif (str_contains($location, ['cafe', 'restaurant', 'coffee'])) {
            return 'cafe';
        } elseif (str_contains($location, ['church', 'temple', 'mosque', 'synagogue'])) {
            return 'religious';
        } elseif (str_contains($location, ['park', 'outdoor', 'garden'])) {
            return 'outdoor';
        } else {
            return 'other';
        }
    }

    private function calculateLocationDiversity($locations)
    {
        if ($locations->isEmpty()) {
            return 0;
        }
        
        $totalLocations = $locations->count();
        $uniqueLocationCount = $locations->unique('location')->count();
        
        return round(($uniqueLocationCount / $totalLocations) * 100, 2);
    }

    private function calculateOccurrences($event, $start, $end)
    {
        if (!$event->is_recurring || $event->recurrence === 'none') {
            return 1;
        }
        
        $count = 0;
        $current = $event->start_time->copy();
        
        while ($current <= $end && $current <= ($event->recurrence_end ?? $end)) {
            if ($current >= $start) {
                $count++;
            }
            
            switch ($event->recurrence) {
                case 'daily':
                    $current->addDay();
                    break;
                case 'weekly':
                    $current->addWeek();
                    break;
                case 'monthly':
                    $current->addMonth();
                    break;
                case 'yearly':
                    $current->addYear();
                    break;
            }
        }
        
        return $count;
    }

    private function getRecurrenceFrequency($recurrence)
    {
        $frequencies = [
            'daily' => 365,
            'weekly' => 52,
            'monthly' => 12,
            'yearly' => 1,
            'none' => 0
        ];
        
        return $frequencies[$recurrence] ?? 0;
    }

    private function calculateTimeSaved($recurringEvents)
    {
        $timeSaved = 0;
        
        foreach ($recurringEvents as $event) {
            $occurrences = $this->calculateOccurrences($event, $event->created_at, now());
            $duration = $event->start_time->diffInMinutes($event->end_time);
            
            // Assuming it takes 5 minutes to create a non-recurring event
            $timeSaved += ($occurrences - 1) * 5;
            
            // Plus time spent in the events themselves
            $timeSaved += ($occurrences - 1) * $duration;
        }
        
        return $timeSaved;
    }

    private function calculateConsistencyScore($recurringEvents)
    {
        if ($recurringEvents->isEmpty()) {
            return 0;
        }
        
        $totalScore = 0;
        
        foreach ($recurringEvents as $event) {
            $ageInDays = $event->created_at->diffInDays(now());
            $expectedOccurrences = $this->calculateExpectedOccurrences($event, $ageInDays);
            $actualOccurrences = $this->calculateOccurrences($event, $event->created_at, now());
            
            if ($expectedOccurrences > 0) {
                $score = ($actualOccurrences / $expectedOccurrences) * 100;
                $totalScore += min(100, $score);
            }
        }
        
        return round($totalScore / $recurringEvents->count(), 2);
    }

    private function calculateExpectedOccurrences($event, $days)
    {
        switch ($event->recurrence) {
            case 'daily':
                return $days;
            case 'weekly':
                return floor($days / 7);
            case 'monthly':
                return floor($days / 30);
            case 'yearly':
                return floor($days / 365);
            default:
                return 1;
        }
    }

    private function generateRecurrenceRecommendations($recurringEvents)
    {
        $recommendations = [];
        $monthlyCount = $recurringEvents->where('recurrence', 'monthly')->count();
        $weeklyCount = $recurringEvents->where('recurrence', 'weekly')->count();
        
        if ($weeklyCount > 5) {
            $recommendations[] = "You have $weeklyCount weekly recurring events. Consider consolidating some into bi-weekly or monthly.";
        }
        
        if ($monthlyCount > 3) {
            $recommendations[] = "You have $monthlyCount monthly recurring events. Review if any can be moved to quarterly.";
        }
        
        $oldEvents = $recurringEvents->filter(function ($event) {
            return $event->created_at->diffInMonths(now()) > 12;
        });
        
        if ($oldEvents->count() > 0) {
            $recommendations[] = "You have {$oldEvents->count()} recurring events older than 1 year. Review if they're still relevant.";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Your recurring events are well-managed. Keep up the good organization!";
        }
        
        return $recommendations;
    }

    private function getMostActiveDay($start, $end)
    {
        $busiestDay = CalendarEvent::select(
                DB::raw('DAYNAME(start_time) as day_name'),
                DB::raw('COUNT(*) as event_count')
            )
            ->whereBetween('start_time', [$start, $end])
            ->groupBy('day_name')
            ->orderBy('event_count', 'desc')
            ->first();
        
        return $busiestDay ? [
            'day' => $busiestDay->day_name,
            'event_count' => $busiestDay->event_count
        ] : null;
    }

    private function identifyImprovementAreas($statusCounts, $statusTrends)
    {
        $areas = [];
        
        $cancellationRate = ($statusCounts['cancelled'] ?? 0) / max(1, array_sum($statusCounts->toArray())) * 100;
        if ($cancellationRate > 10) {
            $areas[] = [
                'area' => 'Cancellation Rate',
                'current' => round($cancellationRate, 1) . '%',
                'target' => '<10%',
                'suggestion' => 'Review events being cancelled frequently and adjust scheduling'
            ];
        }
        
        $inProgressCount = $statusCounts['in_progress'] ?? 0;
        if ($inProgressCount > 5) {
            $areas[] = [
                'area' => 'Events in Progress',
                'current' => $inProgressCount,
                'target' => '<5',
                'suggestion' => 'Complete or resolve events that are stuck in progress'
            ];
        }
        
        return $areas;
    }

    private function generateStatusRecommendations($statusCounts, $completionRate)
    {
        $recommendations = [];
        
        if ($completionRate < 80) {
            $recommendations[] = "Your completion rate is " . round($completionRate, 1) . "%. Aim for at least 80% by following up on pending events.";
        }
        
        $scheduledCount = $statusCounts['scheduled'] ?? 0;
        if ($scheduledCount > 20) {
            $recommendations[] = "You have $scheduledCount scheduled events. Consider if some can be combined or delegated.";
        }
        
        $cancelledCount = $statusCounts['cancelled'] ?? 0;
        if ($cancelledCount > 0) {
            $recommendations[] = "You've cancelled $cancelledCount events. Review cancellation reasons to improve planning.";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Excellent event management! Your status distribution looks healthy.";
        }
        
        return $recommendations;
    }
}