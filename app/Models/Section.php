<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $attributes = ['active' => true];

    protected $table = 'sections';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
