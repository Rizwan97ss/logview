/**
 * Format a size in bytes, such as "1.5 MB".
 */
export function formatBytes(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = bytes;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return unit === 0
        ? `${size} ${units[unit]}`
        : `${size.toFixed(size < 10 ? 1 : 0)} ${units[unit]}`;
}

/**
 * Format an ISO 8601 time as "2026-11-09 10:15:32", keeping the time as the
 * log wrote it rather than converting it to the browser's time zone.
 */
export function formatTime(time: string | null): string {
    if (time === null) {
        return '';
    }

    const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/.exec(time);

    return match === null ? time : `${match[1]} ${match[2]}`;
}

/**
 * Get the first line of a message, for showing it on one row.
 */
export function firstLine(message: string): string {
    const newline = message.indexOf('\n');

    return newline === -1 ? message : message.slice(0, newline);
}

/**
 * Levels from most to least severe, as the toolbar lists them.
 */
export const levelOrder = [
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug',
    'unknown',
];

/**
 * Get the badge colours for a level.
 */
export function levelClasses(level: string | null): string {
    switch (level) {
        case 'emergency':
        case 'alert':
        case 'critical':
            return 'bg-red-700 text-white dark:bg-red-600';
        case 'error':
            return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300';
        case 'warning':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
        case 'notice':
            return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300';
        case 'info':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300';
        case 'debug':
            return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
        default:
            return 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400';
    }
}

/**
 * Split a logged exception out of an entry's context, so it can be shown on
 * its own and left out of the context data.
 */
export function splitException(
    context: Record<string, unknown> | unknown[] | null,
): { exception: string | null; rest: unknown } {
    if (context === null || Array.isArray(context)) {
        return { exception: null, rest: context };
    }

    const { exception, ...rest } = context;

    if (typeof exception === 'string') {
        return { exception: firstLine(exception), rest };
    }

    if (exception !== null && typeof exception === 'object') {
        const { trace: _trace, ...summary } = exception as Record<
            string,
            unknown
        >;

        return { exception: JSON.stringify(summary), rest };
    }

    return { exception: null, rest: context };
}

/**
 * Determine whether a value has anything worth showing.
 */
export function hasContent(value: unknown): boolean {
    if (value === null || value === undefined) {
        return false;
    }

    if (Array.isArray(value)) {
        return value.length > 0;
    }

    return typeof value !== 'object' || Object.keys(value).length > 0;
}
