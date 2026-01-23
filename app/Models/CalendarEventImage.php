<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CalendarEventImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_event_id',
        'image_path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'size',
        'order',
        'is_primary',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_primary' => 'boolean'
    ];

    public function event()
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->image_path);
    }

    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : $this->url;
    }
}