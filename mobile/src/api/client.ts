import { getApiBaseUrl } from '../config';
import { ApiError } from './errors';

export interface ApiOptions {
  method?: string;
  token?: string | null;
  /** JSON body; ignored when `form` is provided. */
  json?: unknown;
  /** Multipart body. */
  form?: FormData;
  query?: Record<string, string | number | undefined>;
}

function buildUrl(path: string, query?: ApiOptions['query']): string {
  const base = getApiBaseUrl();
  const entries = query ? Object.entries(query).filter(([, v]) => v !== undefined) : [];
  const qs = entries.length
    ? '?' + entries.map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`).join('&')
    : '';
  return `${base}/api${path}${qs}`;
}

export async function apiFetch<T = unknown>(path: string, opts: ApiOptions): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (opts.token) headers.Authorization = `Bearer ${opts.token}`;

  let body: BodyInit | undefined;
  if (opts.form) {
    body = opts.form as unknown as BodyInit; // RN sets multipart boundary automatically
  } else if (opts.json !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(opts.json);
  }

  let res: Response;
  try {
    res = await fetch(buildUrl(path, opts.query), {
      method: opts.method ?? (body ? 'POST' : 'GET'),
      headers,
      body,
    });
  } catch (e) {
    // fetch() rejects on network failure (offline, DNS, timeout). Normalize to
    // ApiError (status 0) so every caller can rely on `instanceof ApiError`.
    throw new ApiError(0, 'No se pudo conectar con el servidor. Revisa tu conexión.', e);
  }

  let parsed: unknown = null;
  try {
    parsed = await res.json();
  } catch {
    parsed = null;
  }

  if (!res.ok) {
    const message =
      (parsed as { message?: string } | null)?.message ?? `Request failed (${res.status})`;
    throw new ApiError(res.status, message, parsed);
  }

  return parsed as T;
}
