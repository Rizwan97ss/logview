import type {
    EntriesResponse,
    LogFileDetails,
    LogFileSummary,
    SearchResponse,
} from './types';

export type QueryParams = Record<string, string | undefined>;

/**
 * An error response from the API, with the server's message.
 */
export class ApiError extends Error {
    readonly status: number;

    constructor(message: string, status: number) {
        super(message);
        this.status = status;
    }
}

/**
 * Build a URL from a base, a path and query parameters, leaving out empty
 * parameters.
 */
export function buildUrl(
    baseUrl: string,
    path: string,
    params: QueryParams = {},
): string {
    const query = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') {
            query.set(key, value);
        }
    }

    const queryString = query.toString();

    return (
        baseUrl.replace(/\/+$/, '') +
        path +
        (queryString === '' ? '' : `?${queryString}`)
    );
}

export function createApi(baseUrl: string) {
    async function get<T>(
        path: string,
        params: QueryParams = {},
        signal?: AbortSignal,
    ): Promise<T> {
        const response = await fetch(buildUrl(baseUrl, path, params), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal,
        });

        let body: unknown = null;

        try {
            body = await response.json();
        } catch {
            body = null;
        }

        if (!response.ok) {
            const message =
                body !== null &&
                typeof body === 'object' &&
                typeof (body as { message?: unknown }).message === 'string'
                    ? (body as { message: string }).message
                    : `The request failed with status ${response.status}.`;

            throw new ApiError(message, response.status);
        }

        return body as T;
    }

    return {
        files: (signal?: AbortSignal) =>
            get<{ data: LogFileSummary[] }>('/files', {}, signal),
        file: (id: string, signal?: AbortSignal) =>
            get<{ data: LogFileDetails }>(
                `/files/${encodeURIComponent(id)}`,
                {},
                signal,
            ),
        entries: (id: string, params: QueryParams, signal?: AbortSignal) =>
            get<EntriesResponse>(
                `/files/${encodeURIComponent(id)}/entries`,
                params,
                signal,
            ),
        search: (id: string, params: QueryParams, signal?: AbortSignal) =>
            get<SearchResponse>(
                `/files/${encodeURIComponent(id)}/search`,
                params,
                signal,
            ),
    };
}

export type Api = ReturnType<typeof createApi>;
