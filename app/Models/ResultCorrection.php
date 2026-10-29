<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultCorrection extends Model
{
    protected $attributes = ['status' => 'pending'];

    protected $table = 'result_corrections';

    protected $guarded = ['id'];

    protected $casts = ['old_score' => 'decimal:2', 'new_score' => 'decimal:2', 'old_absent' => 'boolean', 'new_absent' => 'boolean'];

    public function mark()
    {
        return $this->belongsTo(Mark::class);
    }
}
