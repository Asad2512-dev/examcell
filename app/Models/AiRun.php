<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['input' => 'array', 'result' => 'array'];
}
