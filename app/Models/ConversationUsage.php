<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationUsage extends Model
{
    protected $fillable = [
        'user_id',
        'guest_token',
        'date',
        'usage_minutes',
        'is_guest',
        'first_used_at',
        'last_used_at',
    ];
}
