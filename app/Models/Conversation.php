<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'conversations';

    protected $guarded = ['id'];

    protected $casts = [];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
