<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $attributes = ['active' => true];

    protected $table = 'semesters';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];
}
