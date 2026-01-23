<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimony extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',      // Added for Admin posts
        'format',     // Added (text or video)
        'full_name',
        'group',
        'church',
        'testimony',  // The text content
        'video_url',  // The path to the video file
    ];

    /**
     * Relationship: A testimony belongs to a user.
     * This allows you to access $testimony->user->name
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
