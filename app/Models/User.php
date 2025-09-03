<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $fillable = ['name','email','password','profile','role','is_blocked','api_token'];
    protected $hidden = ['password','remember_token','api_token'];
    protected $casts = [
        'profile' => 'array',
        'email_verified_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];

    public function chats()
    {
        return $this->hasMany(Chat::class); // users.id (bigint) -> chats.user_id (bigint)
    }
}
