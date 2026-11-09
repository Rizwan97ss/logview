<?php

use Rss\Vlog\Core\Security\Redactor;

it('hides secrets written in text', function (string $text, string $expected) {
    expect((new Redactor)->text($text))->toBe($expected);
})->with([
    'key and value' => ['Login with password=hunter2 failed', 'Login with password=[redacted] failed'],
    'JSON pair' => ['{"api_key": "abc123", "id": 5}', '{"api_key": "[redacted]", "id": 5}'],
    'key with prefix' => ['csrf_token=abc&next=/home', 'csrf_token=[redacted]&next=/home'],
    'bearer token' => ['Authorization: Bearer abc.def-ghi', 'Authorization: Bearer [redacted]'],
    'authorization header without a scheme' => ['Authorization: abc123', 'Authorization: [redacted]'],
    'basic credentials' => ['header Basic dXNlcjpwYXNz', 'header Basic [redacted]'],
    'password in a URL' => ['mysql://app:s3cret@db:3306/app', 'mysql://app:[redacted]@db:3306/app'],
    'JSON Web Token' => ['jwt eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl', 'jwt [redacted]'],
    'AWS access key' => ['key AKIAIOSFODNN7EXAMPLE used', 'key [redacted] used'],
    'GitHub token' => ['ghp_'.str_repeat('a', 36), '[redacted]'],
    'Stripe key' => ['sk_live_'.str_repeat('a', 24), '[redacted]'],
    'Slack token' => ['xoxb-1234567890-abcdef', '[redacted]'],
    'private key' => ["-----BEGIN RSA PRIVATE KEY-----\nMIIabc\n-----END RSA PRIVATE KEY-----", '[redacted]'],
]);

it('leaves text without secrets unchanged', function (string $text) {
    expect((new Redactor)->text($text))->toBe($text);
})->with([
    'plain message' => ['User 7 placed order 991'],
    'word containing a key name' => ['The tokenizer and password reset page loaded'],
    'author is not auth' => ['author=amy'],
    'URL without a password' => ['https://example.com/path'],
]);

it('hides context values under secret keys at any depth', function () {
    $context = [
        'user' => 'amy',
        'password' => 'hunter2',
        'Api-Key' => 'abc123',
        'auth' => ['user' => 'amy'],
        'author' => 'amy',
        'request' => ['headers' => ['Authorization' => 'Bearer x', 'Accept' => 'json']],
        'tokens' => ['a', 'b'],
        7 => 'list item',
    ];

    expect((new Redactor)->context($context))->toBe([
        'user' => 'amy',
        'password' => '[redacted]',
        'Api-Key' => '[redacted]',
        'auth' => '[redacted]',
        'author' => 'amy',
        'request' => ['headers' => ['Authorization' => '[redacted]', 'Accept' => 'json']],
        'tokens' => '[redacted]',
        7 => 'list item',
    ]);
});

it('hides secrets written in context text', function () {
    expect((new Redactor)->context(['note' => 'retry with token=abc', 'count' => 3]))
        ->toBe(['note' => 'retry with token=[redacted]', 'count' => 3]);
});

it('hides extra keys and patterns', function () {
    $redactor = new Redactor(true, ['iban'], ['/ACME-\d{4}/']);

    expect($redactor->context(['customer_iban' => 'GB33', 'id' => 1]))->toBe(['customer_iban' => '[redacted]', 'id' => 1]);
    expect($redactor->text('Licence ACME-1234'))->toBe('Licence [redacted]');
});

it('rejects an extra pattern that is not a valid regular expression', function () {
    new Redactor(true, [], ['/unclosed(/']);
})->throws(InvalidArgumentException::class, 'The redaction pattern [/unclosed(/] is not a valid regular expression.');

it('changes nothing when turned off', function () {
    $redactor = new Redactor(false);

    expect($redactor->text('password=hunter2'))->toBe('password=hunter2');
    expect($redactor->context(['password' => 'hunter2']))->toBe(['password' => 'hunter2']);
});

it('hides secrets in text that is not valid UTF-8', function () {
    expect((new Redactor)->text("Bad \xFF byte password=hunter2"))->toBe("Bad \xFF byte password=[redacted]");
});
