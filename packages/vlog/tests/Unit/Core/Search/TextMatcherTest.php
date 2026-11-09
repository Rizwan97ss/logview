<?php

use Rss\Vlog\Core\Exceptions\InvalidSearchPatternException;
use Rss\Vlog\Core\Search\TextMatcher;

it('finds a plain term regardless of case by default', function () {
    $matcher = TextMatcher::plain('timeout');

    expect($matcher->matches('Gateway TIMEOUT after 30s'))->toBeTrue();
    expect($matcher->matches('Gateway closed'))->toBeFalse();
});

it('finds a plain term with matching case when case sensitive', function () {
    $matcher = TextMatcher::plain('Timeout', true);

    expect($matcher->matches('Timeout reached'))->toBeTrue();
    expect($matcher->matches('timeout reached'))->toBeFalse();
});

it('ignores the case of non-ASCII letters', function () {
    expect(TextMatcher::plain('ÉCHEC')->matches('Paiement échec'))->toBeTrue();
});

it('treats regular expression characters in a plain term literally', function () {
    expect(TextMatcher::plain('users.(id)')->matches('users.(id) missing'))->toBeTrue();
    expect(TextMatcher::plain('users.(id)')->matches('usersX(id) missing'))->toBeFalse();
});

it('matches regular expressions regardless of case by default', function () {
    $matcher = TextMatcher::regex('order #\d+ failed');

    expect($matcher->matches('Order #991 FAILED'))->toBeTrue();
    expect($matcher->matches('Order #abc failed'))->toBeFalse();
});

it('matches regular expressions with matching case when case sensitive', function () {
    expect(TextMatcher::regex('^Error', true)->matches('error: lowercase'))->toBeFalse();
});

it('accepts patterns containing common delimiter characters', function () {
    expect(TextMatcher::regex('/api/v1#~')->matches('GET /api/v1#~'))->toBeTrue();
});

it('finds a non-ASCII term in text that is not valid UTF-8', function () {
    expect(TextMatcher::plain('échec')->matches("Bad byte \xFF then échec"))->toBeTrue();
});

it('matches a regular expression in text that is not valid UTF-8', function () {
    expect(TextMatcher::regex('then time\w+')->matches("Bad byte \xFF then timeout"))->toBeTrue();
});

it('rejects an invalid regular expression with the reason', function () {
    TextMatcher::regex('order (\d+');
})->throws(InvalidSearchPatternException::class, 'missing closing parenthesis');

it('does not match when a pattern backtracks too much', function () {
    $previous = ini_set('pcre.backtrack_limit', '1000');

    try {
        $matches = TextMatcher::regex('(a+)+$')->matches(str_repeat('a', 30).'b');
    } finally {
        ini_set('pcre.backtrack_limit', (string) $previous);
    }

    expect($matches)->toBeFalse();
});
