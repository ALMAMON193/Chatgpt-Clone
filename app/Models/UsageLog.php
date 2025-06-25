<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    protected $table = 'usage_logs';

    protected $fillable = [
        'user_id',
        'device_id',
        'duration_seconds',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
