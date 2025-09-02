<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id','chat_id','user_id','role','content','metadata'];
    protected $casts = ['metadata' => 'array'];

    public function chat()
    {
        return $this->belongsTo(Chat::class); // uuid FK
    }

    public function user()
    {
        return $this->belongsTo(User::class); // nullable bigint FK
    }
}
