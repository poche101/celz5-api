<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class CalendarService
{
    public function createEvent(array $data, $userId, $images = [])
    {
        $data['user_id'] = $userId;
        $data['is_recurring'] = $data['recurrence'] !== 'none';
        
        // Handle recurrence rules
        if ($data['is_recurring'] && empty($data['recurrence_rules'])) {
            $data['recurrence_rules'] = $this->generateRecurrenceRules($data);
        }
        
        // Create the event
        $event = CalendarEvent::create($data);
        
        // Handle attendees
        if (!empty($data['attendees'])) {
            $this->handleAttendees($event, $data['attendees']);
        }
        
        // Handle images
        if (!empty($images)) {
            $this->storeImages($event, $images);
        }
        
        // Schedule reminders
        if (!empty($data['reminders'])) {
            $this->scheduleReminders($event, $data['reminders']);
        }
        
        return $event;
    }

    public function updateEvent(CalendarEvent $event, array $data, $images = [], $updateScope = 'this')
    {
        if ($event->is_recurring && $updateScope !== 'this') {
            return $this->handleRecurringEventUpdate($event, $data, $updateScope);
        }
        
        $event->update($data);
        
        // Handle new images
        if (!empty($images)) {
            $this->storeImages($event, $images);
        }
        
        // Update reminders if provided
        if (isset($data['reminders'])) {
            $this->updateReminders($event, $data['reminders']);
        }
        
        return $event;
    }

    private function storeImages(CalendarEvent $event, $images)
    {
        foreach ($images as $key => $image) {
            // Store original image
            $path = $image->store("calendar/events/{$event->id}", 'public');
            
            // Create thumbnail
            $thumbnail = Image::make($image)
                ->resize(300, 300, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode($image->getClientOriginalExtension(), 80);
            
            $thumbnailPath = "calendar/events/{$event->id}/thumbnails/" . $image->hashName();
            Storage::disk('public')->put($thumbnailPath, $thumbnail);
            
            // Save image record
            $event->images()->create([
                'image_path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getMimeType(),
                'size' => $image->getSize(),
                'order' => $key,
                'is_primary' => $key === 0
            ]);
        }
    }

    private function generateRecurrenceRules(array $data): array
    {
        $rules = [
            'frequency' => $data['recurrence'],
            'interval' => 1,
            'by_day' => [],
            'by_month_day' => [],
            'by_year_day' => [],
            'exceptions' => []
        ];
        
        $startDate = Carbon::parse($data['start_time']);
        
        switch ($data['recurrence']) {
            case 'weekly':
                $rules['by_day'] = [$startDate->dayOfWeek];
                break;
            case 'monthly':
                $rules['by_month_day'] = [$startDate->day];
                break;
            case 'yearly':
                $rules['by_month_day'] = [$startDate->day];
                $rules['by_month'] = [$startDate->month];
                break;
        }
        
        return $rules;
    }

    private function handleAttendees(CalendarEvent $event, array $attendees)
    {
        $userIds = User::whereIn('email', $attendees)->pluck('id')->toArray();
        
        // Create subscriptions for attendees
        foreach ($userIds as $userId) {
            if ($userId !== $event->user_id) {
                $event->subscriptions()->create([
                    'user_id' => $userId,
                    'permission' => 'viewer',
                    'status' => 'pending',
                    'subscribed_at' => now()
                ]);
            }
        }
    }

    private function scheduleReminders(CalendarEvent $event, array $reminders)
    {
        // This would integrate with your notification system
        // For example, using Laravel Notifications or a job queue
        foreach ($reminders as $minutes) {
            // Schedule reminder notification
            // SendReminderNotification::dispatch($event, $minutes)
            //     ->delay($event->start_time->subMinutes($minutes));
        }
    }

    private function handleRecurringEventUpdate(CalendarEvent $event, array $data, string $scope)
    {
        // Handle recurring event updates based on scope
        // This is a simplified implementation
        switch ($scope) {
            case 'future':
                // Create a new event chain starting from this event
                $newEvent = $event->replicate();
                $newEvent->start_time = Carbon::parse($data['start_time'] ?? $newEvent->start_time);
                $newEvent->save();
                
                // Update current and past events differently if needed
                $event->update($data);
                break;
                
            case 'all':
                // Update all events in the recurrence chain
                CalendarEvent::where('recurrence_rules->chain_id', $event->recurrence_rules['chain_id'] ?? null)
                    ->update($data);
                break;
        }
        
        return $event;
    }

    public function getEventsForPeriod($userId, $start, $end, $filters = [])
    {
        $query = CalendarEvent::visibleTo($userId)
            ->betweenDates($start, $end);
            
        if (!empty($filters['type'])) {
            $query->whereIn('type', (array)$filters['type']);
        }
        
        if (!empty($filters['status'])) {
            $query->whereIn('status', (array)$filters['status']);
        }
        
        return $query->with(['images', 'user'])
            ->orderBy('start_time', 'asc')
            ->get();
    }

    public function generateICalendar(CalendarEvent $event): string
    {
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//YourApp//Calendar//EN\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . uniqid() . "@your-app.com\r\n";
        $ical .= "DTSTAMP:" . now()->format('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART:" . $event->start_time->format('Ymd\THis\Z') . "\r\n";
        $ical .= "DTEND:" . $event->end_time->format('Ymd\THis\Z') . "\r\n";
        $ical .= "SUMMARY:" . $this->escapeString($event->title) . "\r\n";
        $ical .= "DESCRIPTION:" . $this->escapeString($event->description ?? '') . "\r\n";
        $ical .= "LOCATION:" . $this->escapeString($event->location ?? '') . "\r\n";
        $ical .= "URL:" . $this->escapeString($event->meeting_link ?? '') . "\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }

    private function escapeString(string $string): string
    {
        $string = str_replace(["\r\n", "\n"], "\\n", $string);
        $string = str_replace([',', ';', '\\'], ['\\,', '\\;', '\\\\'], $string);
        return $string;
    }
}