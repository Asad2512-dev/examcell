<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $attributes = ['active' => true];

    protected $table = 'courses';

    protected $guarded = ['id'];

    protected $casts = ['credits' => 'decimal:2', 'active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
