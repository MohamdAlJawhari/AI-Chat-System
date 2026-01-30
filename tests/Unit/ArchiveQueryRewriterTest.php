<?php

use App\Services\ArchiveQueryRewriter;
use App\Services\ArchiveRouting\RouterClient;

uses(Tests\TestCase::class);

test('query rewrite returns original when disabled', function () {
    config(['rag.query_rewrite.enabled' => false]);

    $client = \Mockery::mock(RouterClient::class);
    $rewriter = new ArchiveQueryRewriter($client);

    $out = $rewriter->rewrite('Climate policy Egypt');

    expect($out['query'])->toBe('Climate policy Egypt');
    expect($out['used'])->toBeFalse();
    expect($out['source'])->toBe('skip');
});

test('query rewrite handles empty input', function () {
    $client = \Mockery::mock(RouterClient::class);
    $rewriter = new ArchiveQueryRewriter($client);

    $out = $rewriter->rewrite('   ');

    expect($out['query'])->toBe('');
    expect($out['used'])->toBeFalse();
    expect($out['source'])->toBe('skip');
});
