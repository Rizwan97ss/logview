<?php

use Rss\Vlog\Core\Search\SearchFilter;

it('accepts only the chosen levels, written in any case', function () {
    $filter = new SearchFilter(['ERROR', 'warn']);

    expect($filter->acceptsHeader('error', null))->toBeTrue();
    expect($filter->acceptsHeader('warning', null))->toBeTrue();
    expect($filter->acceptsHeader('info', null))->toBeFalse();
    expect($filter->acceptsHeader(null, null))->toBeFalse();
});

it('accepts entries without a recognized level when unknown is chosen', function () {
    expect((new SearchFilter(['unknown']))->acceptsHeader(null, null))->toBeTrue();
});

it('rejects a level that is not recognized', function () {
    new SearchFilter(['verbose']);
})->throws(InvalidArgumentException::class, 'The level [verbose] is not a recognized log level.');

it('accepts entries within the date range, including its limits', function (int $timestamp, bool $expected) {
    $filter = new SearchFilter(null, new DateTimeImmutable('@1000'), new DateTimeImmutable('@2000'));

    expect($filter->acceptsHeader('info', $timestamp))->toBe($expected);
})->with([
    'before' => [999, false],
    'at the start' => [1000, true],
    'at the end' => [2000, true],
    'after' => [2001, false],
]);

it('rejects entries without a known time when a date range is set', function () {
    $filter = new SearchFilter(null, new DateTimeImmutable('@1000'));

    expect($filter->acceptsHeader('info', null))->toBeFalse();
});

it('accepts every entry when no conditions are set', function () {
    $filter = new SearchFilter;

    expect($filter->acceptsHeader(null, null))->toBeTrue();
    expect($filter->hasQuery())->toBeFalse();
    expect($filter->acceptsText('anything'))->toBeTrue();
});

it('treats an empty query as no query', function () {
    expect((new SearchFilter(null, null, null, ''))->hasQuery())->toBeFalse();
});

it('matches text with a plain query or a regular expression', function () {
    expect((new SearchFilter(null, null, null, 'order.id'))->acceptsText('order_id missing'))->toBeFalse();
    expect((new SearchFilter(null, null, null, 'order.id', true))->acceptsText('order_id missing'))->toBeTrue();
});

it('reports its levels as the only condition only when there are no other conditions', function () {
    expect((new SearchFilter(['error']))->onlyLevels())->toBe(['error']);
    expect((new SearchFilter(['error'], new DateTimeImmutable('@1000')))->onlyLevels())->toBeNull();
    expect((new SearchFilter(['error'], null, null, 'failed'))->onlyLevels())->toBeNull();
    expect((new SearchFilter)->onlyLevels())->toBeNull();
});
