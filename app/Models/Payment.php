<?php

namespace App\Models;

use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_subscription_id', 'amount', 'transaction_id', 'payment_method',
        'payment_date', 'status', 'details'
    ];

    public function userSubscription()
    {
        return $this->belongsTo(UserSubscription::class);
    }
}
