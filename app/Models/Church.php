<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Church extends Model
{
    // These are the fields we allow to be saved
    protected $fillable = ['name', 'address', 'area', 'lat', 'lng'];
}
