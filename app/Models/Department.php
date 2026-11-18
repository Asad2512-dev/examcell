<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $attributes = ['active' => true];

    protected $table = 'departments';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];
}
