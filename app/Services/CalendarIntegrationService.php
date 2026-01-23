<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Program;

class CalendarIntegrationService
{
    public function createEventFromStream(Program $program)
    {
        $eventData = [
            'title' => $program->title,
            'description' => $program->description,
            'type' => 'event',
            'start_time' => $program->start_time,
            'end_time' => $program->end_time,
            'location' => $program->location,
            'meeting_link' => $program->stream_link,
            'meeting_platform' => 'custom_stream',
            'visibility' => 'public',
            'custom_fields' => [
                'program_id' => $program->id,
                'stream_type' => 'church_service',
                'allow_comments' => $program->allow_comments,
                'max_attendees' => $program->max_attendees
            ]
        ];
        
        // Create event for the admin/organizer
        $calendarService = app(CalendarService::class);
        $event = $calendarService->createEvent($eventData, $program->user_id);
        
        // Notify subscribed users about the new event
        $this->notifySubscribers($event, $program);
        
        return $event;
    }
    
    public function syncPaymentEvents($payment)
    {
        // Create calendar event for payment reminders or appointments
        if ($payment->type === 'recurring') {
            $eventData = [
                'title' => 'Payment Due: ' . $payment->description,
                'type' => 'reminder',
                'start_time' => $payment->next_payment_date,
                'end_time' => $payment->next_payment_date->addHour(),
                'visibility' => 'private',
                'reminders' => [1440, 60], // 24 hours and 1 hour before
                'custom_fields' => [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'is_recurring' => true
                ]
            ];
            
            $calendarService = app(CalendarService::class);
            return $calendarService->createEvent($eventData, $payment->user_id);
        }
    }
}