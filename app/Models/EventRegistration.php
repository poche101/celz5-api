<?php

// app/Models/EventRegistration.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    protected $fillable = [
        'title',
        'full_name',
        'phone_number',
        'email_address',
        'group_name',
        'church_name',
        'cell_name',
    ];
}
