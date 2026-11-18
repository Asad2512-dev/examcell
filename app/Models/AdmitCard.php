<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmitCard extends Model
{
    protected $table = 'admit_cards';

    protected $guarded = ['id'];

    protected $casts = [];

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
