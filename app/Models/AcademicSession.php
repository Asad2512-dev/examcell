<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    protected $attributes = ['active' => true];

    protected $table = 'academic_sessions';

    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'active' => 'boolean'];
}
