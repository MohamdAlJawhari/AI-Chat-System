<?php

use App\Services\ArchiveFilterRouter;
use App\Services\FilterOptionsService;
use App\Services\ArchiveRouting\Filters\BreakingFilterRouter;
use App\Services\ArchiveRouting\Filters\CategoryFilterRouter;
use App\Services\ArchiveRouting\Filters\CityFilterRouter;
use App\Services\ArchiveRouting\Filters\CountryFilterRouter;
use App\Services\ArchiveRouting\Filters\DateFromFilterRouter;
use App\Services\ArchiveRouting\Filters\DateToFilterRouter;
use App\Services\ArchiveRouting\Filters\WeightsRouter;

uses(Tests\TestCase::class);

test('auto router returns defaults on empty query', function () {
    config(['rag.alpha' => 0.75, 'rag.beta' => 0.25]);

    $router = new ArchiveFilterRouter(
        \Mockery::mock(FilterOptionsService::class),
        \Mockery::mock(CountryFilterRouter::class),
        \Mockery::mock(CategoryFilterRouter::class),
        \Mockery::mock(CityFilterRouter::class),
        \Mockery::mock(DateFromFilterRouter::class),
        \Mockery::mock(DateToFilterRouter::class),
        \Mockery::mock(BreakingFilterRouter::class),
        \Mockery::mock(WeightsRouter::class),
    );

    $out = $router->route('');

    expect($out['filters'])->toBe([]);
    expect($out['weights'])->toBe(['alpha' => 0.75, 'beta' => 0.25]);
    expect($out['source'])->toBe('auto-fallback');
});
