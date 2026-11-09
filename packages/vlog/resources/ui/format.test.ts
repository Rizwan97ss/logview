import { describe, expect, it } from 'vitest';
import { buildUrl } from './api';
import { firstLine, formatBytes, formatTime, hasContent, splitException } from './format';

describe('formatBytes', () => {
    it.each([
        [512, '512 B'],
        [1536, '1.5 KB'],
        [20 * 1024 * 1024, '20 MB'],
        [3 * 1024 ** 3, '3.0 GB'],
    ])('formats %d bytes as %s', (bytes, expected) => {
        expect(formatBytes(bytes)).toBe(expected);
    });
});

describe('formatTime', () => {
    it('keeps the time as written instead of converting it to the browser time zone', () => {
        expect(formatTime('2026-11-09T10:15:32+04:00')).toBe('2026-11-09 10:15:32');
    });

    it('returns other values unchanged and nothing for a missing time', () => {
        expect(formatTime('yesterday')).toBe('yesterday');
        expect(formatTime(null)).toBe('');
    });
});

describe('firstLine', () => {
    it('returns the text before the first line break', () => {
        expect(firstLine('Failed\n#0 trace')).toBe('Failed');
        expect(firstLine('One line')).toBe('One line');
    });
});

describe('splitException', () => {
    it('separates the first line of a Laravel exception from the rest of the context', () => {
        const result = splitException({
            user_id: 7,
            exception: '[object] (Error(code: 0): Boom at /app.php:1)\n[stacktrace]\n#0 {main}',
        });

        expect(result).toEqual({
            exception: '[object] (Error(code: 0): Boom at /app.php:1)',
            rest: { user_id: 7 },
        });
    });

    it('summarizes a Monolog exception without its trace', () => {
        const result = splitException({
            exception: { class: 'RuntimeException', message: 'Boom', trace: ['/app.php:1'] },
        });

        expect(result).toEqual({
            exception: '{"class":"RuntimeException","message":"Boom"}',
            rest: {},
        });
    });

    it('leaves context without an exception unchanged', () => {
        expect(splitException({ id: 1 })).toEqual({ exception: null, rest: { id: 1 } });
        expect(splitException(null)).toEqual({ exception: null, rest: null });
    });
});

describe('hasContent', () => {
    it('is false for empty values and true otherwise', () => {
        expect([null, undefined, [], {}].map(hasContent)).toEqual([false, false, false, false]);
        expect([[1], { a: 1 }, 'text', 0].map(hasContent)).toEqual([true, true, true, true]);
    });
});

describe('buildUrl', () => {
    it('joins the base and path and leaves out empty parameters', () => {
        expect(buildUrl('/vlog/api/', '/files/abc/search', { q: 'a b', regex: undefined, from: '' })).toBe(
            '/vlog/api/files/abc/search?q=a+b',
        );
    });
});
