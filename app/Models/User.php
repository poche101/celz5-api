<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'title',
        'name',
        'email',
        'password',
        'birthday',
        'group',
        'church',
        'cell',
        'kingschat_id',
        'unique_id',
        'profile_picture',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // This automatically generates the Unique ID when a user is created
    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->unique_id)) {
                $user->unique_id = 'CELZ5-' . strtoupper(Str::random(6));
            }
        });
    }
}
