<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmitGenerationRun extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'queued', 'generated' => 0];
}
