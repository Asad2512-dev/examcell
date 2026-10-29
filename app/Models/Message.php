<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';

    protected $guarded = ['id'];

    protected $casts = [];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
