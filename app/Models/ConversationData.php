<?php

namespace App\Models;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Model;

class ConversationData extends Model
{
    protected $table = 'conversation_data';

    protected $fillable = [
        'conversation_id',
        'input_text',
        'output_text',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
