<?php

namespace App\Support;

use Illuminate\Support\Str;

class Text
{
    public static function summarizeTitle(string $text): string
    {
        $line = preg_split('/\r?\n/', trim($text))[0] ?? '';
        $line = trim($line, " \t\-–—•:.");
        return Str::limit($line !== '' ? $line : 'New chat', 60, '…');
    }
}

