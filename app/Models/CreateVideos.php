<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    protected $fillable = [
        'title',
        'description',
        'video_path',
        'poster_path',
        'duration',   // Added for UI
        'episode',    // Added for UI
        'user_id'
    ];

    /**
     * The accessors to append to the model's array form.
     * This makes these keys available automatically in JSON.
     */
    protected $appends = ['thumbnail', 'videoUrl'];

    /**
     * Accessor for Thumbnail (Maps to Flutter's VideoMessage.thumbnail)
     */
    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->poster_path ? asset($this->poster_path) : null,
        );
    }

    /**
     * Accessor for Video URL (Maps to Flutter's VideoMessage.videoUrl)
     */
    protected function videoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->video_path ? asset($this->video_path) : null,
        );
    }

    /**
     * Relationship to User (The uploader/admin)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
