<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomUnavailability extends Model
{
    protected $table = 'room_unavailabilities';

    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
