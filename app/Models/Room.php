<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $attributes = ['active' => true];

    protected $table = 'rooms';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];
}
