<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = ['title', 'description', 'video_path', 'poster_path', 'user_id'];
}
