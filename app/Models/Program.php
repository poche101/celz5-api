<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model {
    protected $fillable = ['title', 'stream_link', 'start_time', 'is_active'];

    public function comments() {
        return $this->hasMany(Comment::class);
    }

    public function attendances() {
        return $this->hasMany(Attendance::class);
    }
}
