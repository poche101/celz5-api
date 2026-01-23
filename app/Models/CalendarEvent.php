<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class CalendarEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'color',
        'start_time',
        'end_time',
        'is_all_day',
        'location',
        'meeting_link',
        'meeting_platform',
        'timezone',
        'recurrence',
        'recurrence_rules',
        'recurrence_end',
        'is_recurring',
        'visibility',
        'status',
        'attendees',
        'reminders',
        'custom_fields'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'recurrence_end' => 'datetime',
        'is_all_day' => 'boolean',
        'is_recurring' => 'boolean',
        'attendees' => 'array',
        'reminders' => 'array',
        'custom_fields' => 'array',
        'recurrence_rules' => 'array'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(CalendarEventImage::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CalendarSubscription::class);
    }

    // Scopes
    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_time', [$start, $end])
              ->orWhereBetween('end_time', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('start_time', '<=', $start)
                     ->where('end_time', '>=', $end);
              });
        });
    }

    public function scopeUpcoming($query, $days = 7)
    {
        return $query->where('start_time', '>=', now())
                     ->where('start_time', '<=', now()->addDays($days))
                     ->orderBy('start_time', 'asc');
    }

    public function scopeVisibleTo($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('visibility', 'public')
              ->orWhereHas('subscriptions', function ($subscription) use ($userId) {
                  $subscription->where('user_id', $userId)
                               ->whereIn('status', ['accepted']);
              });
        });
    }

    // Accessors & Mutators
    public function getDurationAttribute()
    {
        if ($this->is_all_day) {
            return 'All day';
        }
        
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        $duration = $start->diff($end);
        
        return $duration->format('%h hours %i minutes');
    }

    public function getFormattedStartTimeAttribute()
    {
        return $this->start_time->format('Y-m-d\TH:i:s');
    }

    public function getFormattedEndTimeAttribute()
    {
        return $this->end_time->format('Y-m-d\TH:i:s');
    }

    public function getPrimaryImageAttribute()
    {
        return $this->images()->where('is_primary', true)->first();
    }
}