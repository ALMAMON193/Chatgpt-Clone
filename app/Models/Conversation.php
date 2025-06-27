<?php

namespace App\Models;

use App\Models\User;
use App\Models\ConversationData;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'conversations';

    protected $fillable = [
        'user_id',
        'guest_token',
        'name',
        'started_at',
        'ended_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function conversationData()
    {
        return $this->hasMany(ConversationData::class);
    }
}
