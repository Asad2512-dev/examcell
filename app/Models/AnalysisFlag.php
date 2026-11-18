<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisFlag extends Model
{
    protected $table = 'analysis_flags';

    protected $guarded = ['id'];

    protected $casts = ['details' => 'array'];
}
