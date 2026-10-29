<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingScheme extends Model
{
    protected $attributes = ['status' => 'draft', 'rounding_places' => 2];

    protected $table = 'grading_schemes';

    protected $guarded = ['id'];

    protected $casts = [];

    public function boundaries()
    {
        return $this->hasMany(GradeBoundary::class);
    }
}
