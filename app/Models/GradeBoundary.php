<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeBoundary extends Model
{
    protected $table = 'grade_boundaries';

    protected $guarded = ['id'];

    protected $casts = ['minimum' => 'decimal:2', 'grade_points' => 'decimal:2'];
}
