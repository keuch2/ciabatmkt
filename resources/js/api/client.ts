/**
 * Cliente HTTP mínimo sobre fetch para la API de Laravel con sesión por cookie (Sanctum).
 * Antes de cualquier petición que no sea GET se asegura de tener la cookie XSRF-TOKEN.
 */

export type ValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
    readonly status: number;
    readonly errors: ValidationErrors;

    constructor(status: number, message: string, errors: ValidationErrors = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }

    /** Primer mensaje de validación para un campo, si existe. */
    fieldError(field: string): string | undefined {
        return this.errors[field]?.[0];
    }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

function readCookie(name: string): string | null {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`));
    return match ? match.slice(name.length + 1) : null;
}

let csrfRequest: Promise<void> | null = null;

async function ensureCsrfCookie(force = false): Promise<void> {
    if (!force && readCookie('XSRF-TOKEN')) return;
    csrfRequest ??= fetch('/sanctum/csrf-cookie', { credentials: 'include' })
        .then(() => undefined)
        .finally(() => {
            csrfRequest = null;
        });
    await csrfRequest;
}

async function request<T>(method: Method, url: string, body: unknown, retryOnCsrf: boolean): Promise<T> {
    if (method !== 'GET') await ensureCsrfCookie();

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'include',
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    // 419 = token CSRF vencido: renovar la cookie y reintentar una sola vez.
    if (response.status === 419 && retryOnCsrf) {
        await ensureCsrfCookie(true);
        return request<T>(method, url, body, false);
    }

    if (response.status === 204) return undefined as T;

    const data: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const payload = (data ?? {}) as { message?: string; errors?: ValidationErrors };
        throw new ApiError(response.status, payload.message ?? `Error ${response.status}`, payload.errors ?? {});
    }

    return data as T;
}

export function api<T>(method: Method, url: string, body?: unknown): Promise<T> {
    return request<T>(method, url, body, true);
}
