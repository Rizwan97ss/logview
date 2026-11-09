<?php

use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('viewVlog', function ($user = null): bool {
        return true;
    });
});

/**
 * Get the API ID of a log file by its path inside the log folder.
 */
function vlogFileId($test, string $path): string
{
    foreach ($test->getJson('/vlog/api/files')->json('data') as $file) {
        if ($file['path'] === $path) {
            return $file['id'];
        }
    }

    throw new RuntimeException("The log file [{$path}] was not listed.");
}

/**
 * A Laravel log with entries at different levels.
 */
function vlogApiLog(): string
{
    return implode("\n", [
        '[2026-11-01 09:00:00] local.INFO: Order 1 placed {"order_id":1}',
        '[2026-11-02 09:00:00] local.ERROR: Order 2 failed',
        '[2026-11-03 09:00:00] local.WARNING: Order 3 slow',
        '[2026-11-04 09:00:00] local.ERROR: Order 4 failed',
    ])."\n";
}

describe('files', function () {
    it('lists log files in the log folders, most recently modified first', function () {
        touch($this->writeLog('old.log', ''), 1000);
        touch($this->writeLog('nested/new.log', ''), 2000);
        $this->writeLog('notes.txt', '');

        $this->getJson('/vlog/api/files')
            ->assertOk()
            ->assertJsonPath('data.0.path', 'nested/new.log')
            ->assertJsonPath('data.0.name', 'new.log')
            ->assertJsonPath('data.1.path', 'old.log')
            ->assertJsonCount(2, 'data');
    });

    it('does not reveal where log files are on the server', function () {
        $this->writeLog('laravel.log', '');

        $response = $this->getJson('/vlog/api/files');

        expect($response->getContent())->not->toContain(addslashes($this->logDirectory));
        expect($response->getContent())->not->toContain(str_replace('\\', '/', $this->logDirectory));
    });

    it('sends headers that stop responses being cached or sniffed', function () {
        $this->getJson('/vlog/api/files')
            ->assertHeader('Content-Type', 'application/json; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect($this->getJson('/vlog/api/files')->headers->get('Cache-Control'))->toContain('no-store');
    });
});

describe('file', function () {
    it('returns the format and level counts of a file', function () {
        $this->writeLog('laravel.log', vlogApiLog());

        $this->getJson('/vlog/api/files/'.vlogFileId($this, 'laravel.log'))
            ->assertOk()
            ->assertJsonPath('data.format', 'laravel')
            ->assertJsonPath('data.entries', 4)
            ->assertJsonPath('data.levels.error', 2)
            ->assertJsonPath('data.complete', true);
    });

    it('returns 404 for a file that is not in the log folders', function (string $id) {
        $this->writeLog('laravel.log', vlogApiLog());

        $this->getJson('/vlog/api/files/'.$id)
            ->assertNotFound()
            ->assertJsonPath('message', 'Log file not found.');
    })->with([
        'unknown ID' => ['0123456789abcdef0123'],
        'file name' => ['laravel.log'],
        'parent folder' => ['..'],
    ]);

    it('returns 404 for a path to a file outside the log folders', function () {
        $this->writeLog('laravel.log', vlogApiLog());

        $this->getJson('/vlog/api/files/..%2F..%2Fetc%2Fpasswd')->assertNotFound();
        $this->getJson('/vlog/api/files/..%2F..%2Fetc%2Fpasswd/entries')->assertNotFound();
    });
});

describe('entries', function () {
    it('returns a page of entries, newest first', function () {
        $this->writeLog('laravel.log', vlogApiLog());

        $this->getJson('/vlog/api/files/'.vlogFileId($this, 'laravel.log').'/entries?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.message', 'Order 4 failed')
            ->assertJsonPath('data.0.level', 'error')
            ->assertJsonPath('data.0.raw_level', 'ERROR')
            ->assertJsonPath('data.0.channel', 'local')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2);
    });

    it('returns entries oldest first with their context', function () {
        $this->writeLog('laravel.log', vlogApiLog());

        $this->getJson('/vlog/api/files/'.vlogFileId($this, 'laravel.log').'/entries?order=oldest&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.message', 'Order 1 placed')
            ->assertJsonPath('data.0.context.order_id', 1);
    });

    it('rejects invalid paging parameters', function (string $query, string $message) {
        $this->writeLog('laravel.log', vlogApiLog());

        $this->getJson('/vlog/api/files/'.vlogFileId($this, 'laravel.log').'/entries?'.$query)
            ->assertStatus(422)
            ->assertJsonPath('message', $message);
    })->with([
        'page below 1' => ['page=0', 'The page parameter must be a whole number from 1 to '.PHP_INT_MAX.'.'],
        'too many per page' => ['per_page=101', 'The per_page parameter must be a whole number from 1 to 100.'],
        'unknown order' => ['order=sideways', 'The order parameter must be one of: newest, oldest.'],
        'array instead of a value' => ['page[]=1', 'The page parameter must be a string.'],
    ]);

    it('replaces invalid UTF-8 instead of failing', function () {
        $this->writeLog('laravel.log', "[2026-11-01 09:00:00] local.INFO: Bad byte \xFF here\n");

        $this->getJson('/vlog/api/files/'.vlogFileId($this, 'laravel.log').'/entries')
            ->assertOk()
            ->assertJsonPath('data.0.message', "Bad byte \u{FFFD} here");
    });
});

describe('search', function () {
    it('finds entries matching levels and text', function () {
        $this->writeLog('laravel.log', vlogApiLog());

        $this->getJson('/vlog/api/files/'.vlogFileId($this, 'laravel.log').'/search?levels=error,warning&q=order%20[23]&regex=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.message', 'Order 3 slow')
            ->assertJsonPath('data.1.message', 'Order 2 failed')
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.next_cursor', null);
    });

    it('returns a cursor for the next page of results', function () {
        $this->writeLog('laravel.log', vlogApiLog());
        $url = '/vlog/api/files/'.vlogFileId($this, 'laravel.log').'/search?q=order&limit=3';

        $cursor = $this->getJson($url)->assertJsonCount(3, 'data')->json('meta.next_cursor');

        $this->getJson($url.'&cursor='.$cursor)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message', 'Order 1 placed');
    });

    it('rejects invalid search parameters', function (string $query, string $message) {
        $this->writeLog('laravel.log', vlogApiLog());

        $this->getJson('/vlog/api/files/'.vlogFileId($this, 'laravel.log').'/search?'.$query)
            ->assertStatus(422)
            ->assertJsonPath('message', $message);
    })->with([
        'unknown level' => ['levels=verbose', 'The level [verbose] is not a recognized log level.'],
        'invalid date' => ['from=yesterday-ish', 'The from parameter must be a valid date and time.'],
        'invalid boolean' => ['regex=maybe', 'The regex parameter must be true or false.'],
    ]);

    it('rejects an invalid regular expression with the reason', function () {
        $this->writeLog('laravel.log', vlogApiLog());

        $response = $this->getJson('/vlog/api/files/'.vlogFileId($this, 'laravel.log').'/search?regex=1&q=order%20(');

        $response->assertStatus(422);
        expect($response->json('message'))->toContain('missing closing parenthesis');
    });
});
