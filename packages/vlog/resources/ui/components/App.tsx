import { useEffect, useMemo, useRef, useState } from 'preact/hooks';
import { ApiError, createApi } from '../api';
import {
    defaultFilters,
    isFiltering,
    readViewState,
    searchParams,
    writeViewState,
    type Filters,
} from '../filters';
import { formatBytes } from '../format';
import type {
    LogEntry,
    LogFileDetails,
    LogFileSummary,
    PageMeta,
    SearchMeta,
    VlogConfig,
} from '../types';
import { EntryList } from './EntryList';
import { FileList } from './FileList';
import { ThemeToggle } from './ThemeToggle';
import { Toolbar } from './Toolbar';

const PER_PAGE = 50;
const SEARCH_DELAY_MS = 300;
const INDEX_POLL_MS = 1000;

/**
 * Get a readable message from a failed request.
 */
function errorMessage(error: unknown): string {
    if (error instanceof ApiError) {
        return error.message;
    }

    return 'Vlog could not reach the server. Check your connection and try again.';
}

function isAbort(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'AbortError';
}

export function App({ config }: { config: VlogConfig }) {
    const api = useMemo(() => createApi(config.apiUrl), [config.apiUrl]);
    const initial = useMemo(() => readViewState(window.location.search), []);

    const [files, setFiles] = useState<LogFileSummary[] | null>(null);
    const [filesError, setFilesError] = useState<string | null>(null);
    const [fileId, setFileId] = useState<string | null>(initial.fileId);
    const [details, setDetails] = useState<LogFileDetails | null>(null);

    const [filters, setFilters] = useState<Filters>(initial.filters);
    const [queryInput, setQueryInput] = useState(initial.filters.query);
    const [page, setPage] = useState(initial.page);

    const [entries, setEntries] = useState<LogEntry[]>([]);
    const [pageMeta, setPageMeta] = useState<PageMeta | null>(null);
    const [searchMeta, setSearchMeta] = useState<SearchMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [reloadKey, setReloadKey] = useState(0);
    const searchRef = useRef<HTMLInputElement>(null);
    const filtering = isFiltering(filters);

    // Load the file list.
    useEffect(() => {
        const controller = new AbortController();

        api.files(controller.signal)
            .then(({ data }) => {
                setFiles(data);
                setFilesError(null);
                setFileId((current) =>
                    current !== null && data.some((file) => file.id === current)
                        ? current
                        : (data[0]?.id ?? null),
                );
            })
            .catch((caught: unknown) => {
                if (!isAbort(caught)) {
                    setFilesError(errorMessage(caught));
                }
            });

        return () => controller.abort();
    }, [api, reloadKey]);

    // Load the selected file's details, polling while it's still being indexed.
    useEffect(() => {
        if (fileId === null) {
            setDetails(null);

            return;
        }

        const controller = new AbortController();
        let timer: number | undefined;

        const load = (isPoll: boolean) => {
            api.file(fileId, controller.signal)
                .then(({ data }) => {
                    setDetails(data);

                    if (!data.complete) {
                        timer = window.setTimeout(() => load(true), INDEX_POLL_MS);
                    } else if (isPoll) {
                        setReloadKey((key) => key + 1);
                    }
                })
                .catch((caught: unknown) => {
                    if (!isAbort(caught)) {
                        setError(errorMessage(caught));
                    }
                });
        };

        load(false);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [api, fileId, reloadKey]);

    // Wait until typing pauses before searching.
    useEffect(() => {
        if (queryInput === filters.query) {
            return;
        }

        const timer = window.setTimeout(() => {
            setFilters((current) => ({ ...current, query: queryInput }));
        }, SEARCH_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [queryInput, filters.query]);

    // Load entries: a page of everything, or the first page of search results.
    useEffect(() => {
        if (fileId === null) {
            setEntries([]);

            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setError(null);

        const request = filtering
            ? api
                  .search(fileId, searchParams(filters, null, PER_PAGE), controller.signal)
                  .then((response) => {
                      setEntries(response.data);
                      setSearchMeta(response.meta);
                      setPageMeta(null);
                  })
            : api
                  .entries(
                      fileId,
                      { page: String(page), per_page: String(PER_PAGE), order: filters.order },
                      controller.signal,
                  )
                  .then((response) => {
                      setEntries(response.data);
                      setPageMeta(response.meta);
                      setSearchMeta(null);
                  });

        request
            .catch((caught: unknown) => {
                if (!isAbort(caught)) {
                    setEntries([]);
                    setError(errorMessage(caught));
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [api, fileId, filters, filtering, page, reloadKey]);

    // Keep the URL in step with the view, so it can be shared or reloaded.
    useEffect(() => {
        const query = writeViewState({ fileId, page, filters });

        window.history.replaceState(null, '', window.location.pathname + query);
    }, [fileId, page, filters]);

    // Press "/" to jump to the search box.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const typing = target !== null && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);

            if (event.key === '/' && !typing) {
                event.preventDefault();
                searchRef.current?.focus();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    function loadMore() {
        if (fileId === null || searchMeta === null || searchMeta.next_cursor === null) {
            return;
        }

        setLoading(true);

        api.search(fileId, searchParams(filters, searchMeta.next_cursor, PER_PAGE))
            .then((response) => {
                setEntries((current) => [...current, ...response.data]);
                setSearchMeta(response.meta);
            })
            .catch((caught: unknown) => setError(errorMessage(caught)))
            .finally(() => setLoading(false));
    }

    function changeFilters(changes: Partial<Filters>) {
        setFilters((current) => ({ ...current, ...changes }));
        setPage(1);
    }

    function selectFile(id: string) {
        if (id !== fileId) {
            setFileId(id);
            setPage(1);
            setDetails(null);
            setEntries([]);
        }
    }

    const indexedPercent =
        details !== null && details.size > 0
            ? Math.min(100, Math.round((details.indexed_bytes / details.size) * 100))
            : 100;

    return (
        <div class="flex h-full flex-col bg-white text-zinc-900 md:flex-row dark:bg-zinc-950 dark:text-zinc-100">
            <aside class="flex max-h-60 shrink-0 flex-col border-b border-zinc-200 md:max-h-none md:w-72 md:border-r md:border-b-0 dark:border-zinc-800">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <h1 class="text-base font-semibold">{config.title}</h1>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            onClick={() => setReloadKey((key) => key + 1)}
                            title="Reload"
                            aria-label="Reload"
                            class="rounded-md px-2 py-1 text-sm text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        >
                            ↻
                        </button>
                        <ThemeToggle />
                    </div>
                </div>
                <nav class="min-h-0 flex-1 overflow-y-auto" aria-label="Log files">
                    <FileList files={files} selectedId={fileId} error={filesError} onSelect={selectFile} />
                </nav>
            </aside>

            <main class="flex min-h-0 min-w-0 flex-1 flex-col">
                {details !== null && (
                    <header class="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 pt-4">
                        <h2 class="text-lg font-semibold break-all">{details.path}</h2>
                        <span class="text-sm text-zinc-500">
                            {details.entries.toLocaleString()} entries · {formatBytes(details.size)} · {details.format}
                        </span>
                        {!details.complete && (
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                Indexing… {indexedPercent}%
                            </span>
                        )}
                    </header>
                )}

                {fileId !== null && (
                    <Toolbar
                        filters={filters}
                        queryInput={queryInput}
                        levelCounts={details?.levels ?? {}}
                        searchRef={searchRef}
                        onQueryInput={setQueryInput}
                        onChange={changeFilters}
                        onClear={() => {
                            setQueryInput('');
                            changeFilters({ ...defaultFilters, order: filters.order });
                        }}
                    />
                )}

                <div class="min-h-0 flex-1 overflow-y-auto" aria-busy={loading}>
                    {error !== null && (
                        <p role="alert" class="m-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
                            {error}
                        </p>
                    )}

                    {error === null && !loading && fileId !== null && entries.length === 0 && (
                        <p class="p-8 text-center text-sm text-zinc-500">
                            {filtering ? 'No entries match these filters.' : 'This log file has no entries yet.'}
                        </p>
                    )}

                    <EntryList key={`${fileId}-${JSON.stringify(filters)}-${page}`} entries={entries} />

                    {loading && <p class="p-4 text-center text-sm text-zinc-500">Loading…</p>}

                    {!loading && searchMeta !== null && searchMeta.has_more && (
                        <div class="p-4 text-center">
                            {searchMeta.reached_time_limit && (
                                <p class="mb-2 text-xs text-zinc-500">
                                    The search paused to keep the server responsive. More of the file is left to search.
                                </p>
                            )}
                            <button
                                type="button"
                                onClick={loadMore}
                                class="rounded-md border border-zinc-300 px-4 py-1.5 text-sm hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                            >
                                {searchMeta.reached_time_limit ? 'Continue searching' : 'Load more'}
                            </button>
                        </div>
                    )}
                </div>

                {pageMeta !== null && pageMeta.last_page > 1 && (
                    <footer class="flex items-center justify-between border-t border-zinc-200 px-4 py-2 text-sm dark:border-zinc-800">
                        <button
                            type="button"
                            disabled={page <= 1}
                            onClick={() => setPage(page - 1)}
                            class="rounded-md px-3 py-1 hover:bg-zinc-100 disabled:opacity-40 dark:hover:bg-zinc-800"
                        >
                            ← Previous
                        </button>
                        <span class="text-zinc-500 tabular-nums">
                            Page {page.toLocaleString()} of {pageMeta.last_page.toLocaleString()}
                        </span>
                        <button
                            type="button"
                            disabled={page >= pageMeta.last_page}
                            onClick={() => setPage(page + 1)}
                            class="rounded-md px-3 py-1 hover:bg-zinc-100 disabled:opacity-40 dark:hover:bg-zinc-800"
                        >
                            Next →
                        </button>
                    </footer>
                )}
            </main>
        </div>
    );
}
