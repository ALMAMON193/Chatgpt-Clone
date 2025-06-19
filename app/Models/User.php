<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'otp',
        'otp_created_at',
        'otp_expires_at',
        'is_otp_verified',
        'reset_password_token',
        'reset_password_token_expire_at',
        'delete_token',
        'delete_token_expires_at',
        'avatar',
        'user_type',
        'is_verified',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'delete_token',
        'otp',
        'reset_password_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'otp_created_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'reset_password_token_expire_at' => 'datetime',
        'delete_token_expires_at' => 'datetime',
        'is_otp_verified' => 'boolean',
        'is_verified' => 'boolean',
    ];

    // Relationship with conversations
    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}
