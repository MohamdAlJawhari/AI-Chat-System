<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filter options cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache the distinct category/country/city lists (seconds).
    | Default: 12 hours to keep options fresh without hammering the DB.
    |
    */
    'cache_ttl_seconds' => (int) env('FILTER_OPTIONS_CACHE_TTL', 43200),
];
