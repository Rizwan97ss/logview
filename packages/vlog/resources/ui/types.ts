/**
 * The shapes of the JSON the Vlog API returns.
 */

export interface LogFileSummary {
    id: string;
    name: string;
    path: string;
    folder: string;
    size: number;
    modified_at: string;
}

export interface LogFileDetails extends LogFileSummary {
    format: string;
    entries: number;
    levels: Record<string, number>;
    indexed_bytes: number;
    complete: boolean;
}

export interface StackFrame {
    index: number;
    file: string | null;
    line: number | null;
    call: string | null;
}

export interface LogEntry {
    offset: number;
    length: number;
    truncated: boolean;
    level: string | null;
    raw_level: string | null;
    time: string | null;
    channel: string | null;
    message: string;
    context: Record<string, unknown> | unknown[] | null;
    stack: StackFrame[];
}

export interface PageMeta {
    page: number;
    per_page: number;
    total: number;
    last_page: number;
    complete: boolean;
}

export interface SearchMeta {
    next_cursor: number | null;
    has_more: boolean;
    reached_time_limit: boolean;
    complete: boolean;
}

export interface EntriesResponse {
    data: LogEntry[];
    meta: PageMeta;
}

export interface SearchResponse {
    data: LogEntry[];
    meta: SearchMeta;
}

/**
 * Settings the server writes into the page.
 */
export interface VlogConfig {
    apiUrl: string;
    title: string;
}
