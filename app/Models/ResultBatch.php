<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultBatch extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected $table = 'result_batches';

    protected $guarded = ['id'];

    protected $casts = ['published_at' => 'datetime'];

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }

    public function results()
    {
        return $this->hasMany(CalculatedResult::class);
    }
}
