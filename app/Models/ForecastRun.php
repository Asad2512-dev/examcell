<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['evidence' => 'array'];
}
