<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CalendarSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_event_id',
        'user_id',
        'permission',
        'status',
        'subscribed_at'
    ];

    protected $casts = [
        'subscribed_at' => 'datetime'
    ];

    public function event()
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}