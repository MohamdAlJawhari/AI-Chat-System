<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsChunk extends Model
{
    protected $table = 'news_chunks';

    protected $fillable = [
        'news_id', 'chunk_no', 'content', 'token_count',
        'category', 'country', 'city', 'date_sent', 'embedding',
    ];

    protected $casts = [
        'date_sent' => 'datetime',
    ];

    public function news()
    {
        return $this->belongsTo(News::class, 'news_id');
    }
}
