<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $attributes = ['active' => true];

    protected $table = 'rooms';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'accessible_seats' => 'array'];

    public function columns(): int
    {
        return max(1, (int) ($this->seat_columns ?: config('ai.default_columns', 6)));
    }
}
