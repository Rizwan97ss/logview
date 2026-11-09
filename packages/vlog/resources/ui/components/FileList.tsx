import { formatBytes } from '../format';
import type { LogFileSummary } from '../types';

interface Props {
    files: LogFileSummary[] | null;
    selectedId: string | null;
    error: string | null;
    onSelect: (id: string) => void;
}

/**
 * The list of log files, most recently modified first.
 */
export function FileList({ files, selectedId, error, onSelect }: Props) {
    if (error !== null) {
        return <p class="p-4 text-sm text-red-600 dark:text-red-400">{error}</p>;
    }

    if (files === null) {
        return <p class="p-4 text-sm text-zinc-500">Loading files…</p>;
    }

    if (files.length === 0) {
        return (
            <p class="p-4 text-sm text-zinc-500">
                No log files found in the configured folders.
            </p>
        );
    }

    return (
        <ul class="space-y-0.5 p-2">
            {files.map((file) => {
                const selected = file.id === selectedId;

                return (
                    <li key={file.id}>
                        <button
                            type="button"
                            onClick={() => onSelect(file.id)}
                            aria-current={selected ? 'true' : undefined}
                            class={`w-full rounded-md px-3 py-2 text-left transition-colors ${
                                selected
                                    ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                    : 'hover:bg-zinc-100 dark:hover:bg-zinc-800'
                            }`}
                        >
                            <span class="block truncate text-sm font-medium" title={file.path}>
                                {file.path}
                            </span>
                            <span
                                class={`block text-xs ${selected ? 'opacity-75' : 'text-zinc-500'}`}
                            >
                                {formatBytes(file.size)} · {file.folder}
                            </span>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}
