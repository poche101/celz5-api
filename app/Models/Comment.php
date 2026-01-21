<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model {
    protected $fillable = ['user_id', 'program_id', 'message'];

    public function user() {
        // This allows $comment->user->profile_picture
        return $this->belongsTo(User::class);
    }
}
