<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsSummary extends Model
{
    protected $table = 'news_summaries';

    protected $fillable = [
        'news_id',
        'summary',
        'model',
        'status',
        'error',
        'summarized_at',
    ];

    protected $casts = [
        'summarized_at' => 'datetime',
    ];

    public function news()
    {
        return $this->belongsTo(News::class, 'news_id');
    }
}
