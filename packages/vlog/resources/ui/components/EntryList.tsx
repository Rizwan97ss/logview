import type { ComponentChildren } from 'preact';
import { useState } from 'preact/hooks';
import {
    firstLine,
    formatTime,
    hasContent,
    levelClasses,
    splitException,
} from '../format';
import type { LogEntry } from '../types';

interface Props {
    entries: LogEntry[];
}

/**
 * The entries, each expandable to show its details.
 */
export function EntryList({ entries }: Props) {
    const [expanded, setExpanded] = useState<Set<number>>(new Set());

    function toggle(offset: number) {
        setExpanded((current) => {
            const next = new Set(current);

            if (next.has(offset)) {
                next.delete(offset);
            } else {
                next.add(offset);
            }

            return next;
        });
    }

    return (
        <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
            {entries.map((entry) => (
                <EntryRow
                    key={entry.offset}
                    entry={entry}
                    open={expanded.has(entry.offset)}
                    onToggle={() => toggle(entry.offset)}
                />
            ))}
        </ul>
    );
}

interface RowProps {
    entry: LogEntry;
    open: boolean;
    onToggle: () => void;
}

function EntryRow({ entry, open, onToggle }: RowProps) {
    return (
        <li>
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={open}
                class="flex w-full items-start gap-3 px-4 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-900"
            >
                <span
                    class={`mt-0.5 w-20 shrink-0 rounded px-1.5 py-0.5 text-center text-[11px] font-semibold uppercase ${levelClasses(entry.level)}`}
                >
                    {entry.level ?? entry.raw_level ?? 'none'}
                </span>
                <span class="mt-0.5 w-36 shrink-0 font-mono text-xs text-zinc-500 tabular-nums">
                    {formatTime(entry.time)}
                </span>
                {entry.channel !== null && (
                    <span class="mt-0.5 hidden shrink-0 text-xs text-zinc-400 md:inline">
                        {entry.channel}
                    </span>
                )}
                <span class="min-w-0 flex-1 truncate font-mono text-sm">
                    {firstLine(entry.message) || <em class="text-zinc-400">No message</em>}
                </span>
                {entry.stack.length > 0 && (
                    <span class="mt-0.5 shrink-0 text-xs text-zinc-400" title="Has a stack trace">
                        {entry.stack.length} frames
                    </span>
                )}
            </button>
            {open && <EntryDetails entry={entry} />}
        </li>
    );
}

function EntryDetails({ entry }: { entry: LogEntry }) {
    const { exception, rest } = splitException(entry.context);
    const [copied, setCopied] = useState(false);

    async function copy() {
        const text = [entry.message, hasContent(rest) ? JSON.stringify(rest, null, 2) : '']
            .filter((part) => part !== '')
            .join('\n\n');

        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            setCopied(false);
        }
    }

    return (
        <div class="space-y-4 bg-zinc-50 px-4 py-4 text-sm dark:bg-zinc-900/60">
            {entry.truncated && (
                <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                    This entry is larger than the size limit, so only its start is shown.
                </p>
            )}
            <Section title="Message" action={
                <button type="button" onClick={copy} class="text-xs text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">
                    {copied ? 'Copied' : 'Copy'}
                </button>
            }>
                <pre class="font-mono text-xs break-words whitespace-pre-wrap">{entry.message}</pre>
            </Section>
            {exception !== null && (
                <Section title="Exception">
                    <pre class="font-mono text-xs break-words whitespace-pre-wrap text-red-700 dark:text-red-400">{exception}</pre>
                </Section>
            )}
            {entry.stack.length > 0 && (
                <Section title="Stack trace">
                    <ol class="space-y-1 font-mono text-xs">
                        {entry.stack.map((frame) => (
                            <li key={frame.index} class="flex gap-3">
                                <span class="w-6 shrink-0 text-right text-zinc-400">#{frame.index}</span>
                                <span class="min-w-0 break-all">
                                    {frame.file !== null && (
                                        <span class="text-zinc-900 dark:text-zinc-100">
                                            {frame.file}
                                            {frame.line !== null && <span class="text-zinc-500">:{frame.line}</span>}
                                        </span>
                                    )}
                                    {frame.call !== null && (
                                        <span class="block text-zinc-500">{frame.call}</span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ol>
                </Section>
            )}
            {hasContent(rest) && (
                <Section title="Context">
                    <pre class="overflow-x-auto font-mono text-xs">{JSON.stringify(rest, null, 2)}</pre>
                </Section>
            )}
        </div>
    );
}

function Section({ title, action, children }: { title: string; action?: ComponentChildren; children: ComponentChildren }) {
    return (
        <section>
            <div class="mb-1 flex items-center justify-between">
                <h3 class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">{title}</h3>
                {action}
            </div>
            {children}
        </section>
    );
}
