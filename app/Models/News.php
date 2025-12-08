<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = [
        'original_id', 'ref', 'user_id',
        'date_sent', 'date_created', 'date_modified',
        'language', 'is_breaking_news', 'is_milestone',
        'category', 'country', 'city',
        'notes', 'byline', 'title', 'dateline',
        'signature_line', 'introduction', 'description',
        'body', 'content', 'media_type_id', 'path',
        'tags', 'keywords', 'speakers',
    ];

    protected $casts = [
        'date_sent' => 'datetime',
        'date_created' => 'datetime',
        'date_modified' => 'datetime',
        'tags' => 'array',
        'keywords' => 'array',
        'speakers' => 'array',
    ];

    public function chunks()
    {
        return $this->hasMany(NewsChunk::class, 'news_id');
    }
}
