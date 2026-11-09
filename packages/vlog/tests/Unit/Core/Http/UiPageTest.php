<?php

use Rss\Vlog\Core\Http\UiPage;

/**
 * Create built assets in a temporary folder.
 */
function vlogAssets(string $script, string $styles): string
{
    $directory = vlogTemporaryDirectory();
    file_put_contents($directory.'/vlog.js', $script);
    file_put_contents($directory.'/vlog.css', $styles);

    return $directory;
}

it('inlines the built script and styles', function () {
    $html = (new UiPage(vlogAssets('console.log("ready")', 'body{color:red}')))->render('/vlog/api');

    expect($html)->toContain('<style>body{color:red}</style>');
    expect($html)->toContain('<script type="module">console.log("ready")</script>');
    expect($html)->toContain('<div id="vlog"></div>');
});

it('writes the API URL and title into the page config', function () {
    $html = (new UiPage(vlogAssets('', '')))->render('/logs/api', 'Team logs');

    expect($html)->toContain('<script type="application/json" id="vlog-config">{"apiUrl":"/logs/api","title":"Team logs"}</script>');
    expect($html)->toContain('<title>Team logs</title>');
});

it('escapes the config and title so they cannot close their tags', function () {
    $html = (new UiPage(vlogAssets('', '')))->render('/api</script><script>alert(1)</script>', '</title><script>alert(2)</script>');

    expect($html)->not->toContain('<script>alert(1)');
    expect($html)->not->toContain('<script>alert(2)');
    expect($html)->toContain('</script>');
    expect($html)->toContain('&lt;/title&gt;');
});

it('stops built assets from closing their tags early', function () {
    $html = (new UiPage(vlogAssets('const tag = "</script>";', 'a::after{content:"</style>"}')))->render('/vlog/api');

    expect($html)->toContain('const tag = "<\/script>";');
    expect($html)->toContain('content:"<\/style>"');
});

it('adds a Content Security Policy nonce to the inline script and styles', function () {
    $html = (new UiPage(vlogAssets('', '')))->render('/vlog/api', 'Vlog', 'abc123');

    expect($html)->toContain('<style nonce="abc123">');
    expect($html)->toContain('<script type="module" nonce="abc123">');
});

it('explains how to build the interface when the assets are missing', function () {
    $html = (new UiPage(vlogTemporaryDirectory()))->render('/vlog/api');

    expect($html)->toContain('The Vlog interface has not been built.');
});

it('sends headers that stop the page being cached or framed', function () {
    expect((new UiPage)->headers())->toMatchArray([
        'Cache-Control' => 'no-store, private',
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
    ]);
});
