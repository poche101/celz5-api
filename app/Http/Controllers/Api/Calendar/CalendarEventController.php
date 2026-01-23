<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreCalendarEventRequest;
use App\Http\Requests\Calendar\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Services\CalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalendarEventController extends Controller
{
    protected $calendarService;

    public function __construct(CalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Get events for a date range
     */
    public function index(Request $request)
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string']
        ]);

        $start = $request->input('start');
        $end = $request->input('end');
        $filters = $request->only(['type', 'status']);

        $events = $this->calendarService->getEventsForPeriod(
            auth()->id(),
            $start,
            $end,
            $filters
        );

        return response()->json([
            'success' => true,
            'data' => $events,
            'meta' => [
                'total' => $events->count(),
                'start' => $start,
                'end' => $end
            ]
        ]);
    }

    /**
     * Create a new calendar event
     */
    public function store(StoreCalendarEventRequest $request)
    {
        $data = $request->validated();
        $images = $request->file('images', []);

        $event = $this->calendarService->createEvent(
            $data,
            auth()->id(),
            $images
        );

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => $event->load(['images', 'subscriptions'])
        ], 201);
    }

    /**
     * Get a specific event
     */
    public function show(CalendarEvent $event)
    {
        Gate::authorize('view', $event);

        return response()->json([
            'success' => true,
            'data' => $event->load(['images', 'user', 'subscriptions.user'])
        ]);
    }

    /**
     * Update an event
     */
    public function update(UpdateCalendarEventRequest $request, CalendarEvent $event)
    {
        Gate::authorize('update', $event);

        $data = $request->validated();
        $images = $request->file('images', []);
        $updateScope = $request->input('update_scope', 'this');

        $event = $this->calendarService->updateEvent(
            $event,
            $data,
            $images,
            $updateScope
        );

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $event->load(['images', 'subscriptions'])
        ]);
    }

    /**
     * Delete an event
     */
    public function destroy(Request $request, CalendarEvent $event)
    {
        Gate::authorize('delete', $event);

        $deleteScope = $request->input('delete_scope', 'this');
        
        if ($event->is_recurring && $deleteScope !== 'this') {
            // Handle recurring event deletion
            $this->handleRecurringEventDeletion($event, $deleteScope);
        } else {
            $event->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }

    /**
     * Get upcoming events
     */
    public function upcoming()
    {
        $events = CalendarEvent::visibleTo(auth()->id())
            ->upcoming(30) // Next 30 days
            ->with(['images'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
            'meta' => [
                'total' => $events->count(),
                'days' => 30
            ]
        ]);
    }

    /**
     * Export event as iCalendar
     */
    public function export(CalendarEvent $event): StreamedResponse
    {
        Gate::authorize('view', $event);

        $icalContent = $this->calendarService->generateICalendar($event);

        return response()->streamDownload(function () use ($icalContent) {
            echo $icalContent;
        }, "event-{$event->id}.ics", [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="event.ics"'
        ]);
    }

    /**
     * Upload event image
     */
    public function uploadImage(Request $request, CalendarEvent $event)
    {
        Gate::authorize('update', $event);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120']
        ]);

        $this->calendarService->storeImages($event, [$request->file('image')]);

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'data' => $event->images()->latest()->first()
        ]);
    }

    /**
     * Set primary image
     */
    public function setPrimaryImage(CalendarEvent $event, $imageId)
    {
        Gate::authorize('update', $event);

        $event->images()->update(['is_primary' => false]);
        $event->images()->where('id', $imageId)->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Primary image updated successfully'
        ]);
    }

    private function handleRecurringEventDeletion(CalendarEvent $event, string $scope)
    {
        // Implementation for recurring event deletion
        switch ($scope) {
            case 'future':
                // Delete only future occurrences
                CalendarEvent::where('id', '>=', $event->id)
                    ->where('recurrence_rules->chain_id', $event->recurrence_rules['chain_id'] ?? null)
                    ->delete();
                break;
            case 'all':
                // Delete all occurrences
                CalendarEvent::where('recurrence_rules->chain_id', $event->recurrence_rules['chain_id'] ?? null)
                    ->delete();
                break;
            default:
                $event->delete();
        }
    }
}