<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CalendarEvent;

class UpdateCalendarEventRequest extends StoreCalendarEventRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        
        // Make start_time not required for updates
        $rules['start_time'] = ['nullable', 'date'];
        $rules['end_time'] = ['nullable', 'date'];
        
        // Add validation for recurring event updates
        if ($this->calendar_event && $this->calendar_event->is_recurring) {
            $rules['update_scope'] = ['nullable', 'in:this,future,all'];
        }
        
        return $rules;
    }

    protected function prepareForValidation()
    {
        if ($this->has('attendees') && is_string($this->attendees)) {
            $this->merge([
                'attendees' => json_decode($this->attendees, true)
            ]);
        }
        
        if ($this->has('reminders') && is_string($this->reminders)) {
            $this->merge([
                'reminders' => json_decode($this->reminders, true)
            ]);
        }
    }
}