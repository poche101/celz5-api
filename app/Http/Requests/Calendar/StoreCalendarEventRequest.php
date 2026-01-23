<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalendarEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:meeting,appointment,reminder,holiday,event,task'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
            'start_time' => ['required', 'date', 'after_or_equal:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'is_all_day' => ['boolean'],
            'location' => ['nullable', 'string', 'max:500'],
            'meeting_link' => ['nullable', 'url'],
            'meeting_platform' => ['nullable', 'in:zoom,google_meet,teams,webex,other'],
            'timezone' => ['nullable', 'timezone'],
            'recurrence' => ['nullable', 'in:none,daily,weekly,monthly,yearly'],
            'recurrence_rules' => ['nullable', 'array'],
            'recurrence_end' => ['nullable', 'date', 'after:start_time'],
            'visibility' => ['required', 'in:public,private,shared'],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['email', 'exists:users,email'],
            'reminders' => ['nullable', 'array'],
            'reminders.*' => ['integer', 'min:1', 'max:1440'], // minutes before
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'], // 5MB max
            'custom_fields' => ['nullable', 'array']
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.after_or_equal' => 'Start time must be in the future',
            'end_time.after' => 'End time must be after start time',
            'color.regex' => 'Color must be a valid hex color code',
            'images.max' => 'Maximum 5 images allowed per event',
            'images.*.max' => 'Each image must be less than 5MB',
        ];
    }
}
