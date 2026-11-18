<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['verified_at' => 'datetime'];
}
