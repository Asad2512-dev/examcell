<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecheckRequest extends Model
{
    protected $attributes = ['status' => 'pending'];

    protected $table = 'recheck_requests';

    protected $guarded = ['id'];

    protected $casts = [];

    public function result()
    {
        return $this->belongsTo(CalculatedResult::class, 'calculated_result_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
