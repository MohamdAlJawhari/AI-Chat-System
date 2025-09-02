<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    // id is UUID (handled by DB default); no incrementing integer
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id','user_id','title','settings'];
    protected $casts = ['settings' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class); // bigint FK
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }
}
