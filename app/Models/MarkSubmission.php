<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkSubmission extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected $table = 'mark_submissions';

    protected $guarded = ['id'];

    protected $casts = [];
}
