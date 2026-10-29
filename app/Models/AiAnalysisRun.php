<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnalysisRun extends Model
{
    protected $table = 'ai_analysis_runs';

    protected $guarded = ['id'];

    protected $casts = ['summary' => 'array'];
}
